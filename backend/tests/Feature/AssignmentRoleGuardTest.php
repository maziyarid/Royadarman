<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordination_assignment_requires_active_coordinator_and_replaces_previous_owner(): void
    {
        $actor = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);
        $patient = User::factory()->create(['role' => 'patient']);
        $notCoordinator = User::factory()->create(['role' => 'clinician', 'is_active' => true]);
        $inactiveCoordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => false]);
        $validCoordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);

        $case = PatientCase::query()->create([
            'public_reference' => 'RD-'.strtoupper(Str::random(8)),
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => 'in_coordination',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', Str::random()),
            'budget_band' => 'balanced',
            'source_language' => 'fa',
            'version' => 1,
            'current_coordinator_id' => $actor->id,
        ]);

        DB::table('case_assignments')->insert([
            'id' => (string) Str::ulid(),
            'case_id' => $case->id,
            'assignee_user_id' => $actor->id,
            'assigned_by_user_id' => $actor->id,
            'purpose' => 'coordination',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $notCoordinator->id,
                'purpose' => 'coordination',
                'version' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'assignment.role_mismatch');

        $this->actingAs($actor)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $inactiveCoordinator->id,
                'purpose' => 'coordination',
                'version' => 1,
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'assignment.assignee_inactive');

        $this->actingAs($actor)
            ->postJson("/api/v1/staff/cases/{$case->id}/assignments", [
                'assignee_user_id' => $validCoordinator->id,
                'purpose' => 'coordination',
                'version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('patient_cases', [
            'id' => $case->id,
            'current_coordinator_id' => $validCoordinator->id,
            'version' => 2,
        ]);
        $this->assertDatabaseHas('case_assignments', [
            'case_id' => $case->id,
            'assignee_user_id' => $validCoordinator->id,
            'purpose' => 'coordination',
            'released_at' => null,
        ]);
        $this->assertNotNull(DB::table('case_assignments')
            ->where('case_id', $case->id)
            ->where('assignee_user_id', $actor->id)
            ->where('purpose', 'coordination')
            ->value('released_at'));
        $this->assertSame(1, DB::table('case_assignments')
            ->where('case_id', $case->id)
            ->where('purpose', 'coordination')
            ->whereNull('released_at')
            ->count());
    }
}
