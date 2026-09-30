<?php

namespace Tests\Feature;

use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Services\OtpService;
use App\Domain\Identity\Services\SessionAssurance;
use App\Http\Middleware\EnsureRecentAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class RecentAuthCodeCapture implements OtpSender
{
    public string $code = '';

    public function send(string $mobile, string $code, string $locale): void
    {
        $this->code = $code;
    }
}

/**
 * Synthetic data only. NOT RUN when authored: requires independent execution.
 */
class RecentAuthenticationSessionTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE = '/__probe/recent-auth';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-01 12:00:00');
        Route::middleware(['web', EnsureRecentAuthentication::class])
            ->get(self::PROBE, fn () => response()->json(['ok' => true]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'coordinator', 'is_active' => true], $attributes));
    }

    public function test_login_on_another_device_does_not_refresh_an_old_session(): void
    {
        $user = $this->staff();
        $user->forceFill(['last_authenticated_at' => now()])->save();

        $this->actingAs($user)
            ->withSession([SessionAssurance::KEY => now()->subMinutes(45)->getTimestamp()])
            ->getJson(self::PROBE)
            ->assertStatus(423);
    }

    public function test_session_without_assurance_is_rejected_even_if_shared_column_is_fresh(): void
    {
        $user = $this->staff();
        $user->forceFill(['last_authenticated_at' => now()])->save();

        $this->actingAs($user)->getJson(self::PROBE)->assertStatus(423);
    }

    public function test_fresh_session_assurance_is_accepted(): void
    {
        $this->actingAs($this->staff())
            ->withSession([SessionAssurance::KEY => now()->subMinutes(5)->getTimestamp()])
            ->getJson(self::PROBE)
            ->assertOk();
    }

    public function test_thirty_minute_boundary(): void
    {
        $user = $this->staff();

        $this->actingAs($user)
            ->withSession([SessionAssurance::KEY => now()->subMinutes(30)->getTimestamp()])
            ->getJson(self::PROBE)
            ->assertOk();

        $this->actingAs($user)
            ->withSession([SessionAssurance::KEY => now()->subMinutes(30)->subSecond()->getTimestamp()])
            ->getJson(self::PROBE)
            ->assertStatus(423);
    }

    public function test_future_and_non_integer_assurance_values_are_rejected(): void
    {
        $user = $this->staff();

        foreach ([now()->addMinute()->getTimestamp(), (string) now()->getTimestamp(), 'yesterday', null, true] as $value) {
            $this->actingAs($user)
                ->withSession([SessionAssurance::KEY => $value])
                ->getJson(self::PROBE)
                ->assertStatus(423);
        }
    }

    public function test_inactive_user_is_forbidden_even_with_fresh_assurance(): void
    {
        $this->actingAs($this->staff(['is_active' => false]))
            ->withSession([SessionAssurance::KEY => now()->getTimestamp()])
            ->getJson(self::PROBE)
            ->assertForbidden();
    }

    public function test_demo_session_is_forbidden_even_with_fresh_assurance(): void
    {
        $this->actingAs($this->staff())
            ->withSession(['panel_demo' => true, SessionAssurance::KEY => now()->getTimestamp()])
            ->getJson(self::PROBE)
            ->assertForbidden();
    }

    public function test_password_login_marks_the_new_session(): void
    {
        User::factory()->create([
            'role' => 'patient', 'is_active' => true, 'email' => 'recent-auth-synthetic@example.test',
            'username' => 'recent.auth.synthetic', 'password' => 'synthetic-Password-123',
            'phone_hash' => hash('sha256', 'recent-auth-synthetic'),
        ]);

        $this->postJson('/api/v1/auth/password', [
            'username' => 'recent.auth.synthetic', 'password' => 'synthetic-Password-123', 'locale' => 'en',
        ])->assertOk();

        $this->assertNotNull(session(SessionAssurance::KEY));
        $this->assertSame('password', session(SessionAssurance::METHOD_KEY));
    }

    public function test_failed_password_login_does_not_mark_a_session(): void
    {
        User::factory()->create([
            'role' => 'patient', 'is_active' => true, 'email' => 'recent-auth-synthetic2@example.test',
            'username' => 'recent.auth.synthetic2', 'password' => 'synthetic-Password-123',
            'phone_hash' => hash('sha256', 'recent-auth-synthetic2'),
        ]);

        $this->postJson('/api/v1/auth/password', [
            'username' => 'recent.auth.synthetic2', 'password' => 'wrong-password', 'locale' => 'en',
        ])->assertStatus(422);

        $this->assertNull(session(SessionAssurance::KEY));
    }

    public function test_otp_login_marks_the_new_session(): void
    {
        config()->set('royadarman.intake_enabled', false);
        $mobile = '09121112226';
        $hash = hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
        User::factory()->create(['role' => 'patient', 'phone' => $mobile, 'phone_hash' => $hash, 'is_active' => true]);
        $sender = new RecentAuthCodeCapture;
        $this->app->instance(OtpSender::class, $sender);
        $challenge = $this->app->make(OtpService::class)->challenge($mobile, 'en', '127.0.0.1');

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challenge->id, 'code' => $sender->code,
        ])->assertOk();

        $this->assertNotNull(session(SessionAssurance::KEY));
        $this->assertSame('otp', session(SessionAssurance::METHOD_KEY));
    }

    public function test_logout_clears_assurance_and_authentication(): void
    {
        $this->actingAs($this->staff())
            ->withSession([SessionAssurance::KEY => now()->getTimestamp()])
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertGuest();
        $this->assertNull(session(SessionAssurance::KEY));
    }
}
