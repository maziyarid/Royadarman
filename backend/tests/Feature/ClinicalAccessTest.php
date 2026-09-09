<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClinicalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_clinician_credential_revokes_existing_assignment_immediately(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-EXPIRED1', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'clinician_review', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'clinical'), 'budget_band' => 'balanced']);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('practitioners')->insert(['id' => (string) Str::ulid(), 'user_id' => $clinician->id, 'licence_number' => encrypt('LIC-1'), 'licence_hash' => hash('sha256', 'LIC-1'), 'credential_status' => 'verified', 'verified_at' => now()->subDay(), 'expires_at' => now()->subSecond(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($clinician)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }
}
