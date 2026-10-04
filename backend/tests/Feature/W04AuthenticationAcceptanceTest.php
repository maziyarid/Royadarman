<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\SessionAssurance;
use App\Domain\Identity\Services\StaffMfaService;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Support\PhoneHasher;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class W04RecoveryCodeCapture implements OtpSender
{
    public string $code = '';

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->code = $code;
    }
}

/**
 * F-W04-20261004-01, candidate 9a16918997dfa064364fda47c57b18b4f5e40618.
 * NOT RUN when authored. Execute only against an isolated synthetic database.
 * The retrieved-event seam deterministically reproduces a request interleaving;
 * it is not a multi-process MariaDB lock-contention test or live-browser proof.
 * No authentication handler, recovery verifier or persistence operation is mocked.
 */
class W04AuthenticationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'W04-synthetic-password-only!';

    private const RECOVERY_A = 'W04-FIXTURE-A';

    private const RECOVERY_B = 'W04-FIXTURE-B';

    private const SECRET = 'GEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'coordinator',
            'is_active' => true,
            'email' => 'w04-recovery@example.test',
            'username' => 'w04.recovery',
            'password' => self::PASSWORD,
            'phone_hash' => hash('sha256', 'w04-synthetic-identity'),
            // Keep configured MFA after the last recovery code is consumed.
            // Recovery-only enrolment exhaustion remains an explicit policy gate.
            'totp_secret' => self::SECRET,
            'mfa_recovery_codes' => [Hash::make(self::RECOVERY_A), Hash::make(self::RECOVERY_B)],
        ], $attributes));
    }

    private function service(): StaffMfaService
    {
        return $this->app->make(StaffMfaService::class);
    }

    private function passwordRequest(User $user, ?string $recoveryCode, string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson('/api/v1/auth/password', [
            'username' => $user->username,
            'password' => $password,
            'recovery_code' => $recoveryCode,
            'locale' => 'en',
        ]);
    }

    private function assertInventory(User $user, array $expectedCodes): void
    {
        $stored = $user->fresh()->mfa_recovery_codes;
        $this->assertIsArray($stored);
        $this->assertCount(count($expectedCodes), $stored);
        foreach ($expectedCodes as $code) {
            $matches = array_filter($stored, static fn ($hash): bool => is_string($hash) && Hash::check($code, $hash));
            $this->assertCount(1, $matches);
        }
    }

    /**
     * The outer real handler has loaded its User. A separate fresh snapshot
     * consumes a code before the outer handler verifies MFA. The one-shot
     * guard avoids recursion; a cloned dispatcher preserves unrelated listeners.
     */
    private function interleavedConsumption(User $target, string $code, Closure $request): TestResponse
    {
        $dispatcher = User::getEventDispatcher();
        $this->assertNotNull($dispatcher);
        User::setEventDispatcher(clone $dispatcher);
        $interleaved = false;

        try {
            User::retrieved(function (User $loaded) use ($target, $code, &$interleaved): void {
                if ($interleaved || (string) $loaded->getKey() !== (string) $target->getKey()) {
                    return;
                }
                $interleaved = true;
                $competing = User::withoutEvents(fn () => User::query()->findOrFail($loaded->getKey()));
                $this->assertTrue($this->service()->verifyAndConsume($competing, null, $code));
            });
            $response = $request();
        } finally {
            User::setEventDispatcher($dispatcher);
        }

        $this->assertTrue($interleaved, 'The intended real-handler interleaving was not exercised.');

        return $response;
    }

    public function test_stale_model_cannot_consume_the_same_recovery_code_twice(): void
    {
        $user = $this->staff();
        $first = $user->fresh();
        $stale = $user->fresh();

        $this->assertTrue($this->service()->verifyAndConsume($first, null, self::RECOVERY_A));
        $this->assertFalse($this->service()->verifyAndConsume($stale, null, self::RECOVERY_A));
        $this->assertInventory($user, [self::RECOVERY_B]);
    }

    public function test_stale_model_consuming_a_different_code_cannot_restore_a_used_code(): void
    {
        $user = $this->staff();
        $first = $user->fresh();
        $stale = $user->fresh();

        $this->assertTrue($this->service()->verifyAndConsume($first, null, self::RECOVERY_A));
        $this->assertTrue($this->service()->verifyAndConsume($stale, null, self::RECOVERY_B));
        $this->assertInventory($user, []);
        $this->assertFalse($this->service()->verifyAndConsume($user->fresh(), null, self::RECOVERY_A));
    }

    public function test_replacing_the_inventory_invalidates_codes_in_old_models(): void
    {
        $user = $this->staff();
        $stale = $user->fresh();
        $replacement = 'W04-FIXTURE-REPLACEMENT';
        $user->update(['mfa_recovery_codes' => [Hash::make($replacement)]]);

        $this->assertFalse($this->service()->verifyAndConsume($stale, null, self::RECOVERY_A));
        $this->assertInventory($user, [$replacement]);
        $this->assertTrue($this->service()->verifyAndConsume($user->fresh(), null, $replacement));
        $this->assertInventory($user, []);
    }

    public function test_cleared_inventory_is_not_resurrected_by_a_stale_model(): void
    {
        $user = $this->staff();
        $stale = $user->fresh();
        $user->update(['mfa_recovery_codes' => []]);

        $this->assertFalse($this->service()->verifyAndConsume($stale, null, self::RECOVERY_A));
        $this->assertInventory($user, []);
    }

    public function test_inactive_user_cannot_consume_recovery_from_an_old_active_model(): void
    {
        $user = $this->staff();
        $stale = $user->fresh();
        $user->update(['is_active' => false]);

        $this->assertFalse($this->service()->verifyAndConsume($stale, null, self::RECOVERY_A));
        $this->assertInventory($user, [self::RECOVERY_A, self::RECOVERY_B]);
    }

    public function test_unknown_recovery_code_leaves_the_inventory_unchanged(): void
    {
        $user = $this->staff();
        $this->assertFalse($this->service()->verifyAndConsume($user, null, 'W04-NOT-VALID'));
        $this->assertInventory($user, [self::RECOVERY_A, self::RECOVERY_B]);
    }

    public function test_password_route_allows_one_valid_use_then_denies_replay(): void
    {
        $user = $this->staff();
        $this->passwordRequest($user, self::RECOVERY_A)->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('password', session(SessionAssurance::METHOD_KEY));
        $this->assertInventory($user, [self::RECOVERY_B]);

        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->passwordRequest($user, self::RECOVERY_A)->assertStatus(422);
        $this->assertGuest();
        $this->assertNull(session(SessionAssurance::KEY));
    }

    public function test_password_route_denies_the_same_code_consumed_after_user_lookup(): void
    {
        $user = $this->staff();
        $this->interleavedConsumption($user, self::RECOVERY_A, fn () => $this->passwordRequest($user, self::RECOVERY_A))
            ->assertStatus(422);
        $this->assertGuest();
        $this->assertNull(session(SessionAssurance::KEY));
        $this->assertInventory($user, [self::RECOVERY_B]);
    }

    public function test_password_route_does_not_restore_an_interleaved_consumed_code(): void
    {
        $user = $this->staff();
        $this->interleavedConsumption($user, self::RECOVERY_A, fn () => $this->passwordRequest($user, self::RECOVERY_B))
            ->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertInventory($user, []);

        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->passwordRequest($user, self::RECOVERY_A)->assertStatus(422);
        $this->assertGuest();
    }

    public function test_bad_primary_password_cannot_consume_a_valid_recovery_code(): void
    {
        $user = $this->staff();
        $this->passwordRequest($user, self::RECOVERY_A, 'W04-WRONG-PASSWORD')->assertStatus(422);
        $this->assertGuest();
        $this->assertInventory($user, [self::RECOVERY_A, self::RECOVERY_B]);
    }

    public function test_configured_staff_still_need_mfa_and_patient_password_login_still_works(): void
    {
        $user = $this->staff();
        $this->passwordRequest($user, null)->assertStatus(422);
        $this->assertGuest();

        $patient = User::factory()->create([
            'role' => 'patient', 'is_active' => true, 'email' => 'w04-patient@example.test',
            'username' => 'w04.patient', 'password' => self::PASSWORD,
            'phone_hash' => hash('sha256', 'w04-synthetic-patient'),
            'totp_secret' => null, 'mfa_recovery_codes' => null,
        ]);
        $this->passwordRequest($patient, null)->assertOk();
        $this->assertAuthenticatedAs($patient);
    }

    public function test_otp_verify_route_denies_recovery_consumed_after_user_lookup(): void
    {
        // Format-valid synthetic number, routed only to the in-process capture.
        $mobile = '09121112267';
        $sender = new W04RecoveryCodeCapture;
        $this->app->instance(OtpSender::class, $sender);
        config()->set('royadarman.intake_enabled', false);
        $user = $this->staff([
            'phone' => $mobile,
            'phone_hash' => $this->app->make(PhoneHasher::class)->hash($mobile),
        ]);
        $challengeId = $this->postJson('/api/v1/auth/otp/challenge', ['mobile' => $mobile, 'locale' => 'en'])
            ->assertStatus(202)->json('data.challenge_id');
        $this->assertNotSame('', $sender->code);

        $this->interleavedConsumption($user, self::RECOVERY_A, fn () => $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId, 'code' => $sender->code, 'recovery_code' => self::RECOVERY_A,
        ]))->assertStatus(422);

        $this->assertGuest();
        $this->assertNull(session(SessionAssurance::KEY));
        $this->assertInventory($user, [self::RECOVERY_B]);
        $challenge = OtpChallenge::query()->findOrFail($challengeId);
        $this->assertNull($challenge->used_at);
        $this->assertSame(1, (int) $challenge->attempts);

        // A remaining valid factor must still complete the same OTP journey.
        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId, 'code' => $sender->code, 'recovery_code' => self::RECOVERY_B,
        ])->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('otp', session(SessionAssurance::METHOD_KEY));
        $this->assertNotNull($challenge->fresh()->used_at);
        $this->assertInventory($user, []);
    }
}
