<?php

namespace Tests\Feature;

use App\Models\PatientCase;
use App\Models\PatientContactProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class PatientPortalMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_down_refuses_to_drop_patient_location_tables_after_data_exists(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);
        PatientContactProfile::query()->create([
            'user_id' => $patient->id,
            'province' => 'fars',
            'city' => 'شیراز',
            'version' => 1,
        ]);
        $case = PatientCase::query()->create([
            'public_reference' => 'RD-MIGRATION-SAFETY',
            'patient_user_id' => $patient->id,
            'service_type' => 'guidance_referral',
            'status' => 'draft',
            'patient_mobile' => '09121234567',
            'patient_mobile_hash' => hash('sha256', 'migration-safety'),
            'budget_band' => 'call',
            'source_language' => 'fa',
            'version' => 1,
        ]);
        \App\Models\CaseLocation::query()->create([
            'case_id' => $case->id,
            'province' => 'fars',
            'city' => 'شیراز',
        ]);

        $migration = require database_path('migrations/2026_10_04_133000_create_patient_portal_locations.php');

        $this->expectException(RuntimeException::class);
        $migration->down();
    }
}
