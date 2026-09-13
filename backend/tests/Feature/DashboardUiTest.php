<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/fa/dashboard')->assertRedirect('/fa/login');
    }

    public function test_patient_dashboard_renders_role_view(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($patient)
            ->get('/fa/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.roles.patient'), false)
            ->assertSee(__('ui.dashboard.my_cases'), false)
            ->assertSee(__('ui.dashboard.no_cases'), false);
    }

    public function test_clinician_dashboard_renders_credential_block(): void
    {
        $clinician = User::factory()->create(['role' => 'clinician']);
        $this->actingAs($clinician)
            ->get('/fa/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.roles.clinician'), false)
            ->assertSee(__('ui.dashboard.credential'), false)
            ->assertSee(__('ui.dashboard.no_reviews'), false);
    }

    public function test_clinic_rep_dashboard_renders_clinics_block(): void
    {
        $rep = User::factory()->create(['role' => 'clinic_rep']);
        $this->actingAs($rep)
            ->get('/fa/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.roles.clinic_rep'), false)
            ->assertSee(__('ui.dashboard.my_clinics'), false)
            ->assertSee(__('ui.dashboard.no_clinics'), false);
    }

    public function test_coordinator_dashboard_renders_case_queue(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $this->actingAs($coordinator)
            ->get('/fa/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.roles.coordinator'), false)
            ->assertSee(__('ui.dashboard.case_queue'), false)
            ->assertSee(__('ui.dashboard.no_cases_queue'), false);
    }

    public function test_owner_dashboard_renders_aggregate_stats(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)
            ->get('/fa/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.roles.owner'), false)
            ->assertSee(__('ui.dashboard.total_cases'), false)
            ->assertSee(__('ui.dashboard.total_clinics'), false)
            ->assertSee(__('ui.dashboard.case_status_breakdown'), false);
    }

    public function test_tech_admin_dashboard_renders_outbox_stats(): void
    {
        $tech = User::factory()->create(['role' => 'tech_admin']);
        $this->actingAs($tech)
            ->get('/fa/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.roles.tech_admin'), false)
            ->assertSee(__('ui.dashboard.outbox_pending'), false)
            ->assertSee(__('ui.dashboard.recent_audit'), false)
            ->assertSee(__('ui.dashboard.no_audit'), false);
    }

    public function test_dashboard_renders_in_arabic_locale(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($patient)
            ->get('/ar/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.roles.patient', [], 'ar'), false);
    }
}
