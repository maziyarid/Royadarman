<?php

namespace Tests\Feature;

use App\Domain\Patients\ClinicLocationRanking;
use App\Models\CaseLocation;
use App\Models\Clinic;
use App\Models\PatientCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PatientClinicLocationRankingTest extends TestCase
{
    use RefreshDatabase;

    private function careCase(): PatientCase
    {
        $patient = User::factory()->create(['role' => 'patient']);
        return PatientCase::query()->create(['public_reference' => 'RD-LOCATIONTEST', 'patient_user_id' => $patient->id, 'service_type' => 'opg_review', 'status' => 'submitted', 'budget_band' => 'call', 'source_language' => 'fa', 'patient_mobile' => '09121234567', 'patient_mobile_hash' => hash('sha256', 'synthetic-location')]);
    }

    private function clinic(string $name, string $city, ?float $latitude, ?float $longitude): Clinic
    {
        return Clinic::query()->create(['name' => $name, 'city' => $city, 'latitude' => $latitude, 'longitude' => $longitude, 'location_recorded_at' => now(), 'is_active' => true]);
    }

    public function test_nationwide_distance_ranking_excludes_inactive_and_synthetic_clinics(): void
    {
        $case = $this->careCase();
        CaseLocation::query()->create(['case_id' => $case->id, 'province' => 'fars', 'city' => 'شیراز', 'latitude' => 29.59, 'longitude' => 52.58, 'location_consented_at' => now()]);
        $far = $this->clinic('Far Tehran clinic', 'تهران', 35.7, 51.4);
        $near = $this->clinic('Near Shiraz clinic', 'شیراز', 29.60, 52.59);
        $inactive = $this->clinic('Inactive clinic', 'شیراز', 29.59, 52.58); $inactive->update(['is_active' => false]);
        $demo = $this->clinic('Synthetic clinic', 'شیراز', 29.59, 52.58); $demo->update(['synthetic_demo_key' => 'test-fixture']);
        $ranked = app(ClinicLocationRanking::class)->forCase($case);
        $this->assertSame([$near->id, $far->id], $ranked->pluck('id')->all());
        $this->assertGreaterThan(0, $ranked->first()->distance_km);
        $this->assertLessThan(3, $ranked->first()->distance_km);
        $this->assertFalse($ranked->first()->service_verified);
    }

    public function test_no_precise_distance_is_invented_without_consent_or_fresh_clinic_position(): void
    {
        $case = $this->careCase();
        $location = CaseLocation::query()->create(['case_id' => $case->id, 'province' => 'fars', 'city' => 'شیراز', 'latitude' => 29.59, 'longitude' => 52.58]);
        $this->clinic('Located but stale', 'شیراز', 29.60, 52.59)->update(['location_recorded_at' => now()->subYears(2)]);
        $this->clinic('Unknown coordinates', 'شیراز', null, null);
        $this->assertSame([null, null], app(ClinicLocationRanking::class)->forCase($case)->pluck('distance_km')->all());
        $location->update(['location_consented_at' => now()]);
        $this->assertSame([null, null], app(ClinicLocationRanking::class)->forCase($case)->pluck('distance_km')->all());
    }
}
