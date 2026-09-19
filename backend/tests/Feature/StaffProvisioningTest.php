<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\PatientCase;
use App\Models\User;
use App\Support\PhoneHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StaffProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private static bool $failAuditWrites = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::$failAuditWrites = false;
        config()->set('royadarman.phone_hash_key', str_repeat('p', 64));
        config()->set('session.driver', 'database');
        config()->set('session.table', 'sessions');
        config()->set('session.connection', null);
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

    public function test_force_role_change_revokes_existing_sessions(): void
    {
        $hash = app(PhoneHasher::class)->hash('09123334444');
        $user = User::factory()->create([
            'role' => 'coordinator',
            'phone' => '09123334444',
            'phone_hash' => $hash,
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => [hash('sha256', 'unused')],
            'locale' => 'fa',
        ]);
        $this->insertSession('staff-sess', $user->id);

        $this->artisan('royadarman:staff:provision', [
            'mobile' => '09123334444',
            'role' => 'clinician',
            '--force-role-change' => true,
        ])->assertSuccessful();

        $this->assertSame('clinician', $user->refresh()->role->value);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue(
            AuditEvent::query()->where('action', 'session.revoke_all')->get()
                ->contains(fn (AuditEvent $event): bool => $event->reason === 'staff_role_change')
        );
    }

    public function test_reactivating_inactive_staff_revokes_dormant_sessions(): void
    {
        $hash = app(PhoneHasher::class)->hash('09127778888');
        $user = User::factory()->create([
            'role' => 'coordinator',
            'phone' => '09127778888',
            'phone_hash' => $hash,
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => [hash('sha256', 'unused')],
            'locale' => 'fa',
            'is_active' => false,
        ]);
        $this->insertSession('dormant-sess', $user->id);

        $this->artisan('royadarman:staff:provision', [
            'mobile' => '09127778888',
            'role' => 'coordinator',
        ])->assertSuccessful();

        $this->assertTrue($user->refresh()->is_active);
        $this->assertSame('coordinator', $user->role->value);
        $this->assertNotEmpty($user->totp_secret);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertTrue(
            AuditEvent::query()->where('action', 'session.revoke_all')->get()
                ->contains(fn (AuditEvent $event): bool => $event->reason === 'staff_reactivated')
        );
    }

    public function test_same_connection_audit_failure_does_not_leave_elevated_sessions(): void
    {
        $hash = app(PhoneHasher::class)->hash('09125556666');
        $user = User::factory()->create([
            'role' => 'coordinator',
            'phone' => '09125556666',
            'phone_hash' => $hash,
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_recovery_codes' => [hash('sha256', 'unused')],
            'locale' => 'fa',
        ]);
        $this->insertSession('keep-elevated', $user->id);

        $this->causeAuditWritesToFail();

        try {
            $this->artisan('royadarman:staff:provision', [
                'mobile' => '09125556666',
                'role' => 'tech_admin',
                '--force-role-change' => true,
            ]);
            $this->fail('expected audit failure to abort identity change and session revoke');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated audit write failure', $e->getMessage());
        }

        $this->assertSame('coordinator', $user->refresh()->role->value);
        $this->assertSame(1, DB::table('sessions')->where('id', 'keep-elevated')->count());
    }

    private function causeAuditWritesToFail(): void
    {
        self::$failAuditWrites = true;
        AuditEvent::creating(static function (): void {
            if (self::$failAuditWrites) {
                throw new RuntimeException('simulated audit write failure');
            }
        });
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '10.0.0.9',
            'user_agent' => 'Mozilla/5.0 Chrome/120 Windows',
            'payload' => base64_encode('test'),
            'last_activity' => time(),
        ]);
    }
}
