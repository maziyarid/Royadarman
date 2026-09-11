<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NetworkAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_owner_can_open_network_admin(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);

        $this->actingAs($owner)->get('/en/panel/network')->assertOk();
        $this->actingAs($coordinator)->get('/en/panel/network')->assertForbidden();
    }

    public function test_reviewer_membership_requires_verified_clinician(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $clinician = User::factory()->create(['role' => 'clinician', 'is_active' => true, 'name' => 'Reviewer']);

        $this->actingAs($owner)->post('/en/panel/network/clinics', [
            'name' => 'Partner Clinic',
            'city' => 'Tehran',
            'area_code' => 'central',
        ])->assertRedirect();
        $clinicId = DB::table('clinics')->value('id');

        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ])->assertStatus(422);

        $this->actingAs($owner)->post("/en/panel/network/practitioners/{$clinician->id}", [
            'licence_number' => 'DEN-12345',
            'credential_status' => 'verified',
            'expires_at' => now()->addYear()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->actingAs($owner)->post('/en/panel/network/memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
            'active_until' => now()->addYear()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseHas('clinic_memberships', [
            'clinic_id' => $clinicId,
            'user_id' => $clinician->id,
            'membership_role' => 'reviewer',
        ]);
        $this->assertDatabaseHas('practitioners', [
            'user_id' => $clinician->id,
            'credential_status' => 'verified',
        ]);
    }
}
