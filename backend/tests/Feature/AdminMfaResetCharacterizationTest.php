<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CHARACTERIZATION of AdministratorController::resetMfa on main f99210e; not an
 * endorsement. The integrator inspected SessionInventoryService and exercises
 * its actual database revocation below. All identities and sessions are synthetic.
 */
class AdminMfaResetCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'is_active' => true, 'email' => 'owner.synthetic@example.test']);
    }

    private function staffWithMfa(): User
    {
        return User::factory()->create([
            'role' => 'coordinator', 'is_active' => true, 'email' => 'staff.synthetic@example.test',
            'username' => 'staff.synthetic', 'password' => 'synthetic-Password-123',
            'phone_hash' => hash('sha256', 'staff.synthetic'),
            'totp_secret' => 'GEZDGNBVGY3TQOJQ', 'mfa_recovery_codes' => ['x'],
        ]);
    }

    public function test_owner_reset_clears_secret_and_codes_and_is_audited(): void
    {
        $staff = $this->staffWithMfa();
        $owner = $this->owner();
        foreach (['target-one' => $staff, 'target-two' => $staff, 'other-owner' => $owner] as $id => $user) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp,
            ]);
        }

        $this->actingAs($owner)
            ->post('/en/panel/administrators/'.$staff->id.'/reset-mfa')
            ->assertRedirect();

        $fresh = $staff->fresh();
        $this->assertNull($fresh->totp_secret);
        $this->assertNull($fresh->mfa_recovery_codes);
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.mfa_reset', 'resource_id' => (string) $staff->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $staff->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-owner', 'user_id' => $owner->id]);
    }

    public function test_known_gap_after_reset_the_account_logs_in_with_password_alone(): void
    {
        $staff = $this->staffWithMfa();
        $this->actingAs($this->owner())->post('/en/panel/administrators/'.$staff->id.'/reset-mfa');
        auth()->logout();

        $this->postJson('/api/v1/auth/password', [
            'username' => 'staff.synthetic', 'password' => 'synthetic-Password-123', 'locale' => 'en',
        ])->assertOk();
    }

    public function test_non_owner_cannot_reset_and_owner_cannot_reset_self(): void
    {
        $staff = $this->staffWithMfa();
        $technical = User::factory()->create(['role' => 'tech_admin', 'is_active' => true]);

        $this->actingAs($technical)->post('/en/panel/administrators/'.$staff->id.'/reset-mfa')->assertForbidden();
        $this->assertNotNull($staff->fresh()->totp_secret);

        $owner = $this->owner();
        $this->actingAs($owner)->post('/en/panel/administrators/'.$owner->id.'/reset-mfa')->assertStatus(422);
    }
}
