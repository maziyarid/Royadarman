<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\User;
use App\Support\PhoneHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('royadarman.phone_hash_key', str_repeat('p', 64));
    }

    public function test_command_provisions_staff_with_totp_and_recovery_codes(): void
    {
        $this->artisan('royadarman:staff:provision', [
            'mobile' => '09121234567',
            'role' => 'coordinator',
            '--name' => 'Coordinator One',
            '--locale' => 'fa',
        ])->assertSuccessful();

        $hash = app(PhoneHasher::class)->hash('09121234567');
        $user = User::query()->where('phone_hash', $hash)->firstOrFail();

        $this->assertSame('coordinator', $user->role->value);
        $this->assertTrue($user->is_active);
        $this->assertNotEmpty($user->totp_secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $user->totp_secret);
        $this->assertIsArray($user->mfa_recovery_codes);
        $this->assertCount(8, $user->mfa_recovery_codes);
    }

    public function test_command_refuses_to_convert_patient_identity_with_cases(): void
    {
        $hash = app(PhoneHasher::class)->hash('09121111111');
        $patient = User::factory()->create([
            'role' => 'patient',
            'phone' => '09121111111',
            'phone_hash' => $hash,
        ]);
        PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => 'draft',
            'patient_mobile' => '09121111111',
            'patient_mobile_hash' => $hash,
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
        ]);

        $this->artisan('royadarman:staff:provision', [
            'mobile' => '09121111111',
            'role' => 'coordinator',
        ])->assertFailed();

        $this->assertSame('patient', $patient->refresh()->role->value);
        $this->assertNull($patient->refresh()->totp_secret);
    }
}
