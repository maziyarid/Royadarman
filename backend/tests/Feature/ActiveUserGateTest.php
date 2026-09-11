<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveUserGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_authenticated_user_is_logged_out_before_api_access(): void
    {
        $user = User::factory()->create(['role' => 'patient', 'is_active' => false]);

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'account.inactive');

        $this->assertGuest();
    }

    public function test_deactivated_authenticated_user_is_logged_out_before_panel_access(): void
    {
        $user = User::factory()->create(['role' => 'coordinator', 'is_active' => false]);

        $this->actingAs($user)
            ->get('/en/panel')
            ->assertForbidden();

        $this->assertGuest();
    }
}
