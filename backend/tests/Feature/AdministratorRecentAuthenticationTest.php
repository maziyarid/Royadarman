<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\SessionAssurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AdministratorRecentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public static function rejectedProofs(): array
    {
        $cases = [];
        foreach (['store', 'update', 'reset_mfa', 'revoke_sessions'] as $action) {
            foreach (['missing', 'stale', 'future', 'numeric_string', 'boolean', 'array', 'null'] as $proof) {
                $cases[$action.' '.$proof] = [$action, $proof];
            }
        }

        return $cases;
    }

    #[DataProvider('rejectedProofs')]
    public function test_invalid_session_proof_cannot_mutate_staff_even_when_shared_timestamp_is_fresh(string $action, string $proof): void
    {
        $this->freezeTime();
        $owner = $this->owner();
        $owner->forceFill(['last_authenticated_at' => now()])->save();
        $target = $this->target();
        $this->sessions($owner, $target);
        $before = $this->snapshot();
        $session = $proof === 'missing' ? [] : [SessionAssurance::KEY => match ($proof) {
            'stale' => now()->subMinutes(SessionAssurance::MAX_AGE_MINUTES)->subSecond()->timestamp,
            'future' => now()->addSecond()->timestamp,
            'numeric_string' => (string) now()->timestamp,
            'boolean' => true,
            'array' => [now()->timestamp],
            default => null,
        }];

        $this->actingAs($owner)->withSession($session);
        $response = $this->mutation($action, $target);
        $this->assertSame($before, $this->snapshot(), 'Rejected assurance must not change users, audits or target sessions.');
        $response->assertStatus(423);
    }

    public static function actions(): array
    {
        return array_map(fn ($action) => [$action], ['store', 'update', 'reset_mfa', 'revoke_sessions']);
    }

    #[DataProvider('actions')]
    public function test_fresh_owner_session_performs_existing_authorised_mutation(string $action): void
    {
        $this->freezeTime();
        $owner = $this->owner();
        $target = $this->target();
        $this->sessions($owner, $target);
        $this->actingAs($owner)->withSession([SessionAssurance::KEY => now()->subMinutes(30)->timestamp]);
        $this->mutation($action, $target)->assertRedirect('/en/panel/administrators');

        match ($action) {
            'store' => $this->assertDatabaseHas('users', ['name' => 'Synthetic added staff', 'role' => 'coordinator']),
            'update' => $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Synthetic changed staff', 'is_active' => false]),
            'reset_mfa' => $this->assertNull($target->fresh()->totp_secret),
            'revoke_sessions' => $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]),
        };
        $this->assertDatabaseHas('sessions', ['id' => 'synthetic-owner-device', 'user_id' => $owner->id]);
        $this->assertDatabaseHas('audit_events', ['actor_user_id' => $owner->id, 'action' => match ($action) {
            'store' => 'staff.created',
            'update' => 'staff.updated',
            'reset_mfa' => 'staff.mfa_reset',
            'revoke_sessions' => 'session.force_revoke_all',
        }]);
    }

    public function test_fresh_non_owner_and_demo_sessions_keep_existing_denials_without_mutations(): void
    {
        $this->freezeTime();
        $target = $this->target();
        foreach (['patient', 'coordinator', 'clinician', 'clinic_rep', 'tech_admin'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $before = $this->snapshot();
            $this->actingAs($actor)->withSession([SessionAssurance::KEY => now()->timestamp]);
            foreach (['store', 'update', 'reset_mfa', 'revoke_sessions'] as $action) {
                $this->mutation($action, $target)->assertForbidden();
                $this->assertSame($before, $this->snapshot());
            }
        }
        $owner = $this->owner();
        config()->set('royadarman.panel_demo_access', true);
        $before = $this->snapshot();
        $this->actingAs($owner)->withSession(['panel_demo' => true, 'panel_demo_user_id' => (string) $owner->id, SessionAssurance::KEY => now()->timestamp]);
        foreach (['store', 'update', 'reset_mfa', 'revoke_sessions'] as $action) {
            $this->mutation($action, $target)->assertForbidden();
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_fresh_owner_cannot_mutate_self_reserved_demo_identity_or_patient_target(): void
    {
        $this->freezeTime();
        $owner = $this->owner();
        $reserved = $this->target(['email' => 'reserved@royadarman.invalid']);
        $patient = User::factory()->create(['role' => 'patient']);
        $this->actingAs($owner)->withSession([SessionAssurance::KEY => now()->timestamp]);
        $before = $this->snapshot();
        foreach (['update', 'reset_mfa', 'revoke_sessions'] as $action) {
            $this->mutation($action, $owner)->assertStatus(422);
            $this->mutation($action, $reserved)->assertForbidden();
            $this->mutation($action, $patient)->assertNotFound();
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_last_real_owner_guard_is_preserved_with_fresh_assurance(): void
    {
        // Existing count excludes reserved identities. This synthetic fixture
        // reaches that guard without changing its role or demo-session policy.
        $actor = $this->owner(['email' => 'synthetic-owner@royadarman.invalid']);
        $lastRealOwner = $this->owner();
        $before = $this->snapshot();
        $this->actingAs($actor)->withSession([SessionAssurance::KEY => now()->timestamp]);
        $this->mutation('update', $lastRealOwner)->assertRedirect()->assertSessionHasErrors('role');
        $this->assertSame($before, $this->snapshot());
    }

    private function mutation(string $action, User $target): TestResponse
    {
        $base = '/en/panel/administrators';

        return match ($action) {
            'store' => $this->post($base, ['mobile' => '09121112229', 'name' => 'Synthetic added staff', 'role' => 'coordinator', 'locale' => 'en']),
            'update' => $this->patch($base.'/'.$target->id, ['name' => 'Synthetic changed staff', 'role' => $target->role->value, 'locale' => 'en', 'is_active' => false]),
            'reset_mfa' => $this->post($base.'/'.$target->id.'/reset-mfa'),
            'revoke_sessions' => $this->post($base.'/'.$target->id.'/revoke-sessions'),
        };
    }

    private function owner(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'owner', 'email' => 'owner.synthetic@example.test'], $attributes));
    }

    private function target(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'coordinator', 'email' => 'target.synthetic@example.test', 'name' => 'Synthetic original staff', 'totp_secret' => 'GEZDGNBVGY3TQOJQ', 'mfa_recovery_codes' => ['synthetic-hash']], $attributes));
    }

    private function sessions(User $owner, User $target): void
    {
        foreach (['synthetic-target-device' => $target, 'synthetic-owner-device' => $owner] as $id => $user) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp]);
        }
    }

    private function snapshot(): array
    {
        return array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(), ['users', 'audit_events', 'sessions']);
    }
}
