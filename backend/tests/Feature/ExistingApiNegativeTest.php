<?php

namespace Tests\Feature;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Documents\Enums\DocumentStatus;
use App\Models\ClinicalDocument;
use App\Models\OtpChallenge;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExistingApiNegativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_challenge_rejects_an_unknown_locale_without_storing_a_row(): void
    {
        $this->postJson('/api/v1/auth/otp/challenge', [
            'mobile' => '09120000011',
            'locale' => 'de',
        ])->assertStatus(422)->assertJsonPath('error.code', 'error.validation');

        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_expired_otp_challenge_is_rejected_and_not_consumed(): void
    {
        $challenge = $this->challenge('09120000012', '123456', now()->subSecond());

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challenge->id,
            'code' => '123456',
        ])->assertStatus(422)->assertJsonPath('error.code', 'error.validation');

        $this->assertNull($challenge->fresh()->used_at);
        $this->assertGuest();
    }

    public function test_wrong_otp_code_is_rejected_and_is_not_consumed(): void
    {
        $challenge = $this->challenge('09120000013', '123456', now()->addMinute());

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challenge->id,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('error.code', 'error.validation');

        $this->assertNull($challenge->fresh()->used_at);
        $this->assertGuest();
    }

    public function test_unknown_otp_challenge_is_not_found(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => (string) Str::ulid(),
            'code' => '123456',
        ])->assertNotFound();

        $this->assertGuest();
    }

    public function test_inactive_user_otp_verify_is_forbidden_and_does_not_consume_the_challenge(): void
    {
        $mobile = '09120000014';
        $hash = $this->phoneHash($mobile);
        User::factory()->create([
            'role' => 'patient',
            'phone' => $mobile,
            'phone_hash' => $hash,
            'is_active' => false,
        ]);
        $challenge = $this->challenge($mobile, '123456', now()->addMinute(), $hash);

        $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challenge->id,
            'code' => '123456',
        ])->assertForbidden();

        $this->assertNull($challenge->fresh()->used_at);
        $this->assertGuest();
    }

    public function test_password_login_rejects_unknown_inactive_and_demo_identities(): void
    {
        $this->postJson('/api/v1/auth/password', [
            'username' => 'nobody',
            'password' => 'secret-secret',
            'locale' => 'fa',
        ])->assertStatus(422);

        $inactive = User::factory()->create([
            'role' => 'patient',
            'username' => 'inactive',
            'password' => Hash::make('secret-secret'),
            'phone_hash' => $this->phoneHash('09120000015'),
            'email' => 'inactive@example.test',
            'is_active' => false,
        ]);
        $this->postJson('/api/v1/auth/password', [
            'username' => 'inactive',
            'password' => 'secret-secret',
            'locale' => 'en',
        ])->assertStatus(422);
        $this->assertGuest();
        $this->assertNull($inactive->fresh()->last_authenticated_at);

        User::factory()->create([
            'role' => 'patient',
            'username' => 'demouser',
            'password' => Hash::make('secret-secret'),
            'phone_hash' => $this->phoneHash('09120000016'),
            'email' => 'demo@royadarman.invalid',
            'is_active' => true,
        ]);
        $this->postJson('/api/v1/auth/password', [
            'username' => 'demouser',
            'password' => 'secret-secret',
            'locale' => 'ar',
        ])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_guest_is_rejected_on_every_authenticated_operation(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized()
            ->assertJsonPath('error.code', 'error.unauthenticated');
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me/sessions')->assertUnauthorized();
        $this->deleteJson('/api/v1/me/sessions/missing-session')->assertUnauthorized();
        $this->postJson('/api/v1/me/sessions/revoke-others')->assertUnauthorized();
        $this->postJson('/api/v1/me/sessions/revoke-all')->assertUnauthorized();
        $this->postJson('/api/v1/cases/'.Str::ulid().'/documents')->assertUnauthorized();
        $this->getJson('/api/v1/cases/'.Str::ulid().'/documents/'.Str::ulid())->assertUnauthorized();
    }

    public function test_inactive_cookie_session_is_forbidden_and_then_unauthenticated(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'is_active' => false]);

        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'account.inactive');

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_the_cookie_session(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_current_session_cannot_be_revoked_through_delete(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'is_active' => true]);
        $sessionId = Str::random(40);
        $session = $this->app['session'];
        $session->setId($sessionId);
        $session->start();
        $session->save();

        $this->withCredentials()
            ->withCookie((string) config('session.cookie'), $sessionId)
            ->actingAs($user)
            ->deleteJson('/api/v1/me/sessions/'.$sessionId)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'session.cannot_revoke_current');

        $this->withCredentials()
            ->withCookie((string) config('session.cookie'), $sessionId)
            ->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    public function test_delete_of_another_users_session_is_not_found_and_the_row_remains(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        DB::table('sessions')->insert([
            'id' => 'other-session-id-0001',
            'user_id' => $other->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'synthetic',
            'payload' => base64_encode('synthetic'),
            'last_activity' => time(),
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/v1/me/sessions/other-session-id-0001')
            ->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => 'other-session-id-0001', 'user_id' => $other->id]);
    }

    public function test_revoke_all_ends_the_cookie_session(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/v1/me/sessions/revoke-all')
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);

        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_demo_session_cannot_call_write_or_profile_apis_but_can_log_out(): void
    {
        config()->set('royadarman.panel_demo_access', true);
        $user = User::factory()->create(['role' => 'patient', 'is_active' => true]);
        $session = ['panel_demo' => true, 'panel_demo_user_id' => (string) $user->id];

        $this->actingAs($user)->withSession($session)
            ->getJson('/api/v1/me')
            ->assertForbidden();
        $this->actingAs($user)->withSession($session)
            ->postJson('/api/v1/me/sessions/revoke-others')
            ->assertForbidden();
        $this->actingAs($user)->withSession($session)
            ->postJson('/api/v1/me/sessions/revoke-all')
            ->assertForbidden();
        $this->actingAs($user)->withSession($session)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);
    }

    public function test_wrong_role_cannot_upload_or_read_a_patient_document(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $case = $this->makeCase($patient);
        $document = ClinicalDocument::query()->create([
            'case_id' => $case->id,
            'uploaded_by_user_id' => $patient->id,
            'storage_disk' => 'private-opg',
            'storage_key' => 'synthetic/'.Str::ulid(),
            'original_name' => 'opg.png',
            'detected_mime' => 'image/png',
            'byte_size' => 8,
            'sha256' => hash('sha256', 'synthetic'),
            'status' => DocumentStatus::Approved,
        ]);
        $otherCase = $this->makeCase($patient);

        $this->actingAs($coordinator)
            ->postJson('/api/v1/cases/'.$case->id.'/documents')
            ->assertNotFound();
        $this->actingAs($coordinator)
            ->getJson('/api/v1/cases/'.$case->id.'/documents/'.$document->id)
            ->assertNotFound();
        $this->actingAs($patient)
            ->getJson('/api/v1/cases/'.$otherCase->id.'/documents/'.$document->id)
            ->assertNotFound();
        $this->assertDatabaseCount('clinical_documents', 1);
    }

    private function phoneHash(string $mobile): string
    {
        return hash_hmac('sha256', $mobile, (string) config('royadarman.phone_hash_key'));
    }

    private function challenge(string $mobile, string $code, \DateTimeInterface $expiresAt, ?string $hash = null): OtpChallenge
    {
        return OtpChallenge::query()->create([
            'phone' => $mobile,
            'phone_hash' => $hash ?? $this->phoneHash($mobile),
            'code_hash' => Hash::make($code),
            'locale' => 'fa',
            'expires_at' => $expiresAt,
            'last_sent_at' => now()->subMinute(),
            'request_ip_hash' => hash('sha256', 'synthetic'),
        ]);
    }

    private function makeCase(User $patient): PatientCase
    {
        return PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => CaseStatus::Submitted,
            'patient_mobile' => '09120000021',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);
    }
}
