<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PrivilegedActionReauthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_owner_session_is_blocked_from_administrator_mutations(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'is_active' => true,
            'last_authenticated_at' => now()->subHours(2),
        ]);
        $target = User::factory()->create(['role' => 'coordinator']);

        $this->actingAs($owner)
            ->post('/fa/panel/administrators', [
                'mobile' => '09121234567',
                'name' => 'New Staff',
                'role' => 'coordinator',
                'locale' => 'fa',
            ])
            ->assertStatus(423);

        $this->actingAs($owner)
            ->patch('/fa/panel/administrators/'.$target->id, [
                'name' => 'Renamed',
                'role' => 'coordinator',
                'locale' => 'fa',
                'is_active' => true,
            ])
            ->assertStatus(423);

        $this->actingAs($owner)
            ->post('/fa/panel/administrators/'.$target->id.'/reset-mfa')
            ->assertStatus(423);

        $this->actingAs($owner)
            ->post('/fa/panel/administrators/'.$target->id.'/revoke-sessions')
            ->assertStatus(423);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => $target->name]);
    }

    public function test_fresh_owner_session_is_allowed_past_reauthentication_gate(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'is_active' => true,
            'last_authenticated_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->post('/fa/panel/administrators', [
                'mobile' => '09121234599',
                'name' => 'Fresh Staff',
                'role' => 'coordinator',
                'locale' => 'fa',
            ]);

        $this->assertNotSame(423, $response->status());
    }

    public function test_session_revocation_is_audited(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'is_active' => true,
            'last_authenticated_at' => now(),
        ]);
        $target = User::factory()->create(['role' => 'coordinator', 'is_active' => true]);

        $this->actingAs($owner)
            ->post('/fa/panel/administrators/'.$target->id.'/revoke-sessions')
            ->assertRedirect();

        $this->assertDatabaseHas('audit_events', [
            'actor_user_id' => $owner->id,
            'action' => 'staff.sessions_revoked',
            'resource_type' => 'user',
            'resource_id' => (string) $target->id,
        ]);
    }

    public function test_stale_superadmin_session_is_also_blocked(): void
    {
        config()->set('session.driver', 'array');
        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
            'last_authenticated_at' => now()->subHours(2),
        ]);
        $target = User::factory()->create(['role' => 'coordinator']);

        $this->actingAs($superadmin)
            ->post('/fa/panel/administrators/'.$target->id.'/revoke-sessions')
            ->assertStatus(423);
    }
}
