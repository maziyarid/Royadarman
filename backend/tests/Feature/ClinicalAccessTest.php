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

    public function test_business_owner_has_no_implicit_clinical_case_access(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $owner = User::factory()->create(['role' => 'owner']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-OWNER1', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'clinician_review', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'owner'), 'budget_band' => 'balanced']);
        $this->actingAs($owner)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }

    public function test_technical_administrator_has_no_implicit_clinical_case_access(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $techAdmin = User::factory()->create(['role' => 'tech_admin']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-TECH1', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'clinician_review', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'tech'), 'budget_band' => 'balanced']);
        $this->actingAs($techAdmin)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }

    public function test_clinic_representative_has_no_access_before_grant(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinicRep = User::factory()->create(['role' => 'clinic_rep']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-REP1', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'in_coordination', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'rep'), 'budget_band' => 'balanced']);
        $this->actingAs($clinicRep)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }

    public function test_unverified_clinician_has_no_clinical_case_access(): void
    {
        config()->set('royadarman.intake_enabled', true);
        $patient = User::factory()->create(['role' => 'patient']);
        $clinician = User::factory()->create(['role' => 'clinician']);
        $case = PatientCase::query()->create(['public_reference' => 'RD-UNVERIFIED', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'clinician_review', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'unverified'), 'budget_band' => 'balanced']);
        DB::table('case_assignments')->insert(['id' => (string) Str::ulid(), 'case_id' => $case->id, 'assignee_user_id' => $clinician->id, 'assigned_by_user_id' => $clinician->id, 'purpose' => 'clinical_review', 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('practitioners')->insert(['id' => (string) Str::ulid(), 'user_id' => $clinician->id, 'licence_number' => encrypt('LIC-PEND'), 'licence_hash' => hash('sha256', 'LIC-PEND'), 'credential_status' => 'pending', 'verified_at' => null, 'expires_at' => now()->addYear(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($clinician)->getJson('/api/v1/cases/'.$case->id)->assertNotFound();
    }
}
