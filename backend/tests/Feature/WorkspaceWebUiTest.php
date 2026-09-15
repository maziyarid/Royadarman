<?php

namespace Tests\Feature;

use App\Domain\Support\Enums\SupportCategory;
use App\Models\HomeServiceRequest;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkspaceWebUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_can_open_support_profile_and_home_service_workspace(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'en', 'name' => 'Sara Patient']);

        $this->actingAs($patient)->get('/en/panel/support')
            ->assertOk()
            ->assertSee(__('panel.nav.support'), false)
            ->assertSee(__('panel.support.empty'), false)
            ->assertSee('id="new-thread"', false);

        $this->actingAs($patient)->get('/en/panel/profile')
            ->assertOk()
            ->assertSee('Sara Patient')
            ->assertSee('name="locale"', false);

        $this->actingAs($patient)->get('/en/panel/home-service')
            ->assertOk()
            ->assertSee(__('panel.home.tehran_only'), false)
            ->assertSee(__('ui.dashboard.no_home_service'), false);
    }

    public function test_patient_can_open_a_support_thread_and_clinician_cannot(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'en']);
        $clinician = User::factory()->create(['role' => 'clinician', 'locale' => 'en']);
        $conversation = SupportConversation::query()->create([
            'patient_user_id' => $patient->id,
            'subject' => 'Question about a referral',
            'category' => SupportCategory::Referral,
            'status' => 'open',
            'priority' => 'normal',
            'opened_at' => now(),
            'source_language' => 'en',
        ]);

        $this->actingAs($patient)->get('/en/panel/support/'.$conversation->id)
            ->assertOk()
            ->assertSee('Question about a referral');

        $this->actingAs($clinician)->get('/en/panel/support')->assertForbidden();
        $this->actingAs($clinician)->get('/en/panel/support/'.$conversation->id)->assertNotFound();
    }

    public function test_coordinator_sees_home_service_and_owner_does_not(): void
    {
        $coordinator = User::factory()->create(['role' => 'coordinator', 'locale' => 'en']);
        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'en']);
        $patient = User::factory()->create(['role' => 'patient']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-HOME-UI-1',
            'patient_user_id' => $patient->id,
            'service_type' => 'home_dentistry',
            'status' => 'home_visit_proposed',
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', 'home-ui'),
            'budget_band' => 'balanced',
            'source_language' => 'en',
            'budget_input_unit' => 'toman',
            'currency' => 'IRR',
            'tehran_area' => 'north',
        ]);
        $home = HomeServiceRequest::query()->create([
            'case_id' => $case->id,
            'patient_user_id' => $patient->id,
            'tehran_area' => 'north',
            'status' => 'coordinator_review',
            'version' => 1,
        ]);
        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $coordinator->id,
            'assigned_by_user_id' => $coordinator->id,
            'purpose' => 'coordination',
            'assigned_at' => now(),
            'released_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($coordinator)->get('/en/panel/home-service')
            ->assertOk()
            ->assertSee('RD-HOME-UI-1')
            ->assertSee(__('request.areas.north'), false);

        $this->actingAs($coordinator)->get('/en/panel/home-service/'.$home->id)
            ->assertOk()
            ->assertSee(__('panel.home.transition'), false);

        $this->actingAs($owner)->get('/en/panel/home-service')->assertForbidden();
    }

    public function test_profile_locale_update_redirects_to_the_chosen_locale(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'en', 'name' => 'Old Name']);

        $this->actingAs($patient)->patch('/en/panel/profile', [
            'locale' => 'fa',
            'name' => 'نام تازه',
        ])->assertRedirect('/fa/panel/profile');

        $patient->refresh();
        $this->assertSame('fa', $patient->locale);
        $this->assertSame('نام تازه', $patient->name);
    }

    public function test_owner_marketing_and_network_use_the_workspace_shell(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'locale' => 'en']);

        $this->actingAs($owner)->get('/en/panel/marketing')
            ->assertOk()
            ->assertSee(__('panel.cms.title'), false)
            ->assertSee('app-shell', false)
            ->assertSee(__('panel.cms.empty'), false);

        $this->actingAs($owner)->get('/en/panel/network')
            ->assertOk()
            ->assertSee(__('network.title'), false)
            ->assertSee('app-shell', false)
            ->assertSee(__('network.new_clinic'), false);
    }

    public function test_dashboard_case_rows_link_to_the_case_workspace(): void
    {
        $patient = User::factory()->create(['role' => 'patient', 'locale' => 'en']);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-DASH-LINK',
            'patient_user_id' => $patient->id,
            'service_type' => 'opg_review',
            'status' => 'submitted',
            'patient_mobile' => '09120000000',
            'patient_mobile_hash' => hash('sha256', 'dash'),
            'budget_band' => 'balanced',
            'source_language' => 'en',
            'budget_input_unit' => 'toman',
            'currency' => 'IRR',
        ]);

        $this->actingAs($patient)->get('/en/dashboard')
            ->assertOk()
            ->assertSee('RD-DASH-LINK')
            ->assertSee('/en/panel/cases/'.$case->id, false);
    }
}
