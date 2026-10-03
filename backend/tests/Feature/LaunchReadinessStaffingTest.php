<?php

namespace Tests\Feature;

use App\Models\Practitioner;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LaunchReadinessStaffingTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_active_demo_accounts_do_not_prove_real_staffing(): void
    {
        $this->coordinator(PanelDemoRegistry::identity('coordinator')['email']);
        $this->clinician(PanelDemoRegistry::identity('clinician')['email']);
        $this->assertCounts(0, 0);
    }

    public function test_mixed_real_and_demo_accounts_count_only_real_staff(): void
    {
        $this->coordinator(PanelDemoRegistry::identity('coordinator')['email']);
        $this->clinician(PanelDemoRegistry::identity('clinician')['email']);
        $this->coordinator('real-coordinator@example.test');
        $this->clinician('real-clinician@example.test');
        $this->assertCounts(1, 1);
    }

    public function test_null_email_and_nonreserved_email_accounts_preserve_existing_eligibility(): void
    {
        $this->coordinator(null);
        $this->clinician(null);
        // Exact reserved identities, not a newly invented whole-domain exclusion.
        $this->coordinator('genuine-coordinator@royadarman.invalid');
        $this->clinician('genuine-clinician@royadarman.invalid');
        $this->assertCounts(2, 2);
    }

    public function test_inactive_unverified_and_expired_real_staff_still_do_not_count(): void
    {
        $this->freezeTime();
        $this->coordinator('inactive-coordinator@example.test', false);
        $this->clinician('inactive-clinician@example.test', active: false);
        $this->clinician('pending-clinician@example.test', credential: 'pending');
        $this->clinician('expired-clinician@example.test', expiry: now()->subSecond());
        $this->clinician('at-expiry-clinician@example.test', expiry: now());
        $this->assertCounts(0, 0);
        $this->coordinator('active-coordinator@example.test');
        $this->clinician('permanent-clinician@example.test', expiry: null);
        $this->assertCounts(1, 1);
    }

    public function test_staffing_projection_remains_private_count_only_and_technical_admin_read_only(): void
    {
        $coordinator = $this->coordinator('PRIVATE-STAFF-EMAIL@example.test');
        $coordinator->update(['name' => 'PRIVATE-STAFF-NAME']);
        $this->clinician('PRIVATE-CLINICIAN@example.test');
        $response = $this->actingAs(User::factory()->create(['role' => 'tech_admin']))->get('/en/panel/launch-readiness')->assertOk();
        $response->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('PRIVATE-STAFF-NAME')
            ->assertDontSee('PRIVATE-STAFF-EMAIL')->assertDontSee('PRIVATE-CLINICIAN')->assertDontSee('SYNTHETIC-LICENCE')
            ->assertDontSee('data-launch-acknowledgement', false);
    }

    public function test_other_roles_inactive_owner_and_demo_session_keep_existing_denials(): void
    {
        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/en/panel/launch-readiness')->assertForbidden();
        }
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => false]);
        $this->actingAs($owner)->get('/en/panel/launch-readiness')->assertForbidden();
        $owner->update(['is_active' => true]);
        $this->actingAs($owner)->withSession(['panel_demo' => true])->get('/en/panel/launch-readiness')->assertForbidden();
    }

    private function assertCounts(int $coordinators, int $clinicians): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/en/panel/launch-readiness')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('gates', fn ($gates) => $gates['staffing_coordinator']['detail'] === (string) $coordinators
                && $gates['staffing_coordinator']['ok'] === ($coordinators > 0)
                && $gates['staffing_clinician']['detail'] === (string) $clinicians
                && $gates['staffing_clinician']['ok'] === ($clinicians > 0));
    }

    private function coordinator(?string $email, bool $active = true): User
    {
        return User::factory()->create(['role' => 'coordinator', 'email' => $email, 'is_active' => $active]);
    }

    private function clinician(?string $email, bool $active = true, string $credential = 'verified', mixed $expiry = 'future'): User
    {
        $user = User::factory()->create(['role' => 'clinician', 'email' => $email, 'is_active' => $active]);
        Practitioner::query()->create([
            'user_id' => $user->id, 'licence_number' => 'SYNTHETIC-LICENCE', 'licence_hash' => hash('sha256', 'SYNTHETIC-'.$user->id),
            'credential_status' => $credential, 'verified_at' => now()->subDay(), 'expires_at' => $expiry === 'future' ? now()->addYear() : $expiry,
        ]);

        return $user;
    }
}
