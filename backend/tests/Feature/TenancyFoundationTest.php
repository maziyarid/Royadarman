<?php

namespace Tests\Feature;

use App\Domain\Identity\Services\SessionAssurance;
use App\Domain\Identity\Tenancy\WorkspaceAccess;
use App\Models\AuditEvent;
use App\Models\Clinic;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

/** Real new module routes, synthetic persisted records. Shared route inclusion is W01's integration hunk. */
class TenancyFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Load the production module route file, not test-only controllers or replacement guards.
        // The missing-file branch permits the initial real HTTP404 red test before implementation.
        if (is_file(base_path('routes/tenancy.php')) && ! Route::has('tenancy.organisations.store')) {
            require base_path('routes/tenancy.php');
        }
    }

    private function owner(): User
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($user)->withSession([
            SessionAssurance::KEY => now()->getTimestamp(),
            SessionAssurance::METHOD_KEY => 'password',
        ]);

        return $user;
    }

    private function organisation(string $label): string
    {
        $clinic = Clinic::query()->create(['name' => $label, 'city' => 'Synthetic', 'is_active' => true]);
        $this->postJson('/tenancy/v1/organisations', [
            'clinic_id' => $clinic->id, 'display_name' => $label,
        ])->assertCreated()->assertJsonPath('data.clinic_id', $clinic->id);

        return $clinic->id;
    }

    private function branch(string $clinic, string $code): string
    {
        return $this->postJson('/tenancy/v1/organisations/'.$clinic.'/branches', [
            'code' => $code, 'name' => 'Synthetic '.$code,
        ])->assertCreated()->json('data.id');
    }

    private function member(string $clinic, string $branch, User $user, string $role = 'receptionist', ?string $until = null): string
    {
        return $this->postJson('/tenancy/v1/organisations/'.$clinic.'/memberships', [
            'branch_id' => $branch, 'user_id' => $user->id, 'workspace_role' => $role, 'active_until' => $until,
        ])->assertCreated()->json('data.id');
    }

    public function test_owner_creates_two_persisted_clinics_with_multiple_branches(): void
    {
        $this->owner();
        $a = $this->organisation('Clinic A');
        $b = $this->organisation('Clinic B');
        $branches = [$this->branch($a, 'north'), $this->branch($a, 'south'), $this->branch($b, 'north'), $this->branch($b, 'south')];
        $this->assertCount(4, array_unique($branches));
        $this->assertDatabaseCount('organisations', 2);
        $this->assertDatabaseCount('clinic_branches', 4);
        $this->assertDatabaseCount('clinic_memberships', 0); // Existing reader remains independent.
        $this->assertDatabaseCount('workspace_memberships', 4); // Initial owner recovery membership per branch.
        $this->getJson('/tenancy/v1/workspaces')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_selected_memberships_do_not_union_clinics_or_roles(): void
    {
        $this->owner();
        $a = $this->organisation('A');
        $b = $this->organisation('B');
        $aa = $this->branch($a, 'a');
        $bb = $this->branch($b, 'b');
        $user = User::factory()->create(['role' => 'patient']);
        $m1 = $this->member($a, $aa, $user);
        $m2 = $this->member($b, $bb, $user, 'accountant');
        $this->actingAs($user)->postJson('/tenancy/v1/workspace', ['membership_id' => $m1])
            ->assertOk()->assertJsonPath('data.clinic_id', $a)->assertJsonPath('data.workspace_role', 'receptionist');
        $this->getJson('/tenancy/v1/workspace')->assertOk()->assertJsonPath('data.branch_id', $aa);
        $access = app(WorkspaceAccess::class);
        $ctx = $access->resolve($user->id, $m1);
        $this->assertSame($a, $access->authorize($ctx, 'scheduling.write')->clinicId);
        $this->assertFalse($access->allows($ctx, 'finance.read'));
        $this->assertFalse($access->allows($ctx, 'clinical.sign'));
        $this->actingAs($user)->postJson('/tenancy/v1/workspace', ['membership_id' => $m2])
            ->assertOk()->assertJsonPath('data.clinic_id', $b);
        $this->assertSame($b, $access->authorize($access->resolve($user->id, $m2), 'finance.read')->clinicId);
        $this->assertFalse($access->allows($access->resolve($user->id, $m2), 'scheduling.write'));
        $this->assertFalse($access->allows($access->resolve($user->id, $m2), 'owner.metrics'));
    }

    public function test_cross_tenant_branch_and_foreign_membership_tampering_is_denied(): void
    {
        $this->owner();
        $a = $this->organisation('A');
        $b = $this->organisation('B');
        $bb = $this->branch($b, 'b');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->postJson('/tenancy/v1/organisations/'.$a.'/memberships', [
            'branch_id' => $bb, 'user_id' => $user->id, 'workspace_role' => 'receptionist',
        ])->assertNotFound();
        $membership = $this->member($b, $bb, $other);
        $this->actingAs($user)->postJson('/tenancy/v1/workspace', ['membership_id' => $membership])->assertNotFound();
        $this->getJson('/tenancy/v1/workspaces')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_duplicate_assignment_is_stable_and_revocation_cannot_be_replayed_into_activation(): void
    {
        $owner = $this->owner();
        $a = $this->organisation('A');
        $branch = $this->branch($a, 'main');
        $user = User::factory()->create();
        $id = $this->member($a, $branch, $user);
        $this->assertSame($id, $this->member($a, $branch, $user));
        $this->assertSame(1, DB::table('workspace_memberships')->where('user_id', $user->id)->count());
        $access = app(WorkspaceAccess::class);
        $ctx = $access->resolve($user->id, $id);
        $this->actingAs($user)->postJson('/tenancy/v1/workspace', ['membership_id' => $id])->assertOk();
        $this->actingAs($owner)->deleteJson('/tenancy/v1/organisations/'.$a.'/memberships/'.$id, ['version' => 1])
            ->assertOk()->assertJsonPath('data.version', 2);
        $this->actingAs($user)->getJson('/tenancy/v1/workspace')->assertNotFound();
        $this->assertFalse($access->allows($ctx, 'scheduling.write')); // Held object is never authority.
        $this->actingAs($owner)->postJson('/tenancy/v1/organisations/'.$a.'/memberships', [
            'branch_id' => $branch, 'user_id' => $user->id, 'workspace_role' => 'receptionist',
        ])->assertStatus(409);
        $this->assertNotNull(DB::table('workspace_memberships')->where('id', $id)->value('revoked_at'));
    }

    public function test_membership_expiry_and_inactive_account_or_branch_are_rechecked(): void
    {
        $this->owner();
        $a = $this->organisation('A');
        $branch = $this->branch($a, 'main');
        $user = User::factory()->create();
        $id = $this->member($a, $branch, $user, 'receptionist', now()->addMinute()->toIso8601String());
        $this->actingAs($user)->postJson('/tenancy/v1/workspace', ['membership_id' => $id])->assertOk();
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $this->getJson('/tenancy/v1/workspace')->assertNotFound();
        DB::table('users')->where('id', $user->id)->update(['is_active' => true]);
        DB::table('clinic_branches')->where('id', $branch)->update(['is_active' => false]);
        $this->getJson('/tenancy/v1/workspace')->assertNotFound();
        DB::table('clinic_branches')->where('id', $branch)->update(['is_active' => true]);
        $this->travel(61)->seconds();
        $this->getJson('/tenancy/v1/workspace')->assertNotFound();
        $this->travelBack();
        DB::table('workspace_memberships')->where('id', $id)->update(['active_from' => now()->addHour()]);
        $this->getJson('/tenancy/v1/workspace')->assertNotFound();
    }

    public function test_management_titles_never_create_clinical_signing_authority(): void
    {
        $owner = $this->owner();
        $a = $this->organisation('A');
        $branch = $this->branch($a, 'main');
        $clinician = User::factory()->create(['role' => 'clinician']);
        $payload = ['branch_id' => $branch, 'user_id' => $clinician->id, 'workspace_role' => 'dentist'];
        $this->postJson('/tenancy/v1/organisations/'.$a.'/memberships', $payload)->assertStatus(422);
        DB::table('practitioners')->insert([
            'id' => (string) Str::ulid(), 'user_id' => $clinician->id, 'licence_number' => 'synthetic-test-only',
            'licence_hash' => hash('sha256', 'synthetic-'.$clinician->id), 'credential_status' => 'verified',
            'verified_at' => now(), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $this->member($a, $branch, $clinician, 'dentist');
        $this->actingAs($clinician)->postJson('/tenancy/v1/workspace', ['membership_id' => $id])->assertOk();
        $this->assertFalse(app(WorkspaceAccess::class)->allows(app(WorkspaceAccess::class)->resolve($clinician->id, $id), 'clinical.sign'));
        DB::table('practitioners')->where('user_id', $clinician->id)->update(['expires_at' => now()->subSecond()]);
        $this->getJson('/tenancy/v1/workspace')->assertNotFound();
        $ownerMember = DB::table('workspace_memberships')->where('user_id', $owner->id)->value('id');
        $this->assertFalse(app(WorkspaceAccess::class)->allows(app(WorkspaceAccess::class)->resolve($owner->id, $ownerMember), 'clinical.sign'));
    }

    public function test_last_branch_owner_is_protected_and_second_owner_allows_revocation(): void
    {
        $owner = $this->owner();
        $a = $this->organisation('A');
        $branch = $this->branch($a, 'main');
        $id = DB::table('workspace_memberships')->where('user_id', $owner->id)->value('id');
        $this->deleteJson('/tenancy/v1/organisations/'.$a.'/memberships/'.$id, ['version' => 1])->assertStatus(409);
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $this->member($a, $branch, $otherOwner, 'owner');
        $this->deleteJson('/tenancy/v1/organisations/'.$a.'/memberships/'.$id, ['version' => 1])->assertOk();
        $this->assertNotNull(DB::table('workspace_memberships')->where('id', $id)->value('revoked_at'));
    }

    public function test_management_requires_active_owner_and_current_session_proof(): void
    {
        $owner = $this->owner();
        $a = $this->organisation('A');
        $this->withSession([SessionAssurance::KEY => now()->subHour()->getTimestamp()]);
        $this->postJson('/tenancy/v1/organisations/'.$a.'/branches', ['code' => 'stale', 'name' => 'Denied'])->assertStatus(423);
        $this->withSession([SessionAssurance::KEY => now()->getTimestamp()]);
        $patient = User::factory()->create();
        $this->actingAs($patient)->postJson('/tenancy/v1/organisations/'.$a.'/branches', ['code' => 'patient', 'name' => 'Denied'])->assertForbidden();
        $this->assertDatabaseCount('clinic_branches', 0);
        $this->actingAs($owner)->postJson('/tenancy/v1/organisations/'.$a.'/branches', ['code' => 'valid', 'name' => 'Allowed'])->assertCreated();
    }

    public function test_composite_foreign_key_rejects_cross_clinic_reference_even_without_http(): void
    {
        $this->owner();
        $a = $this->organisation('A');
        $b = $this->organisation('B');
        $bb = $this->branch($b, 'b');
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('workspace_memberships')->insert([
            'id' => (string) Str::ulid(), 'clinic_id' => $a, 'branch_id' => $bb, 'user_id' => $user->id,
            'workspace_role' => 'receptionist', 'active_from' => now(), 'version' => 1,
            'created_by_user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_audit_failure_rolls_back_new_branch_and_owner_membership(): void
    {
        $this->owner();
        $a = $this->organisation('A');
        AuditEvent::creating(function (AuditEvent $event): void {
            if ($event->action === 'tenancy.branch.created') {
                throw new \RuntimeException('Synthetic audit storage failure');
            }
        });
        try {
            $this->postJson('/tenancy/v1/organisations/'.$a.'/branches', ['code' => 'failure', 'name' => 'Synthetic'])->assertStatus(500);
            $this->assertDatabaseCount('clinic_branches', 0);
            $this->assertDatabaseCount('workspace_memberships', 0);
        } finally {
            AuditEvent::flushEventListeners();
        }
    }

    public function test_consumer_transaction_commits_a_scoped_write_and_returns_its_result(): void
    {
        $this->assertTrue(method_exists(WorkspaceAccess::class, 'run'), 'Consumers need a real transaction envelope.');
        $this->owner();
        $clinic = $this->organisation('Consumer');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user);
        $access = app(WorkspaceAccess::class);
        $context = $access->resolve($user->id, $id);
        $level = DB::transactionLevel();
        $calls = 0;
        $result = $access->run($context, 'scheduling.write', function ($current) use ($level, &$calls) {
            $calls++;
            $this->assertSame($level + 1, DB::transactionLevel());

            return DB::table('clinic_branches')->where('clinic_id', $current->clinicId)->where('id', $current->branchId)
                ->update(['name' => 'Committed synthetic write']);
        });
        $this->assertSame(1, $result);
        $this->assertSame(1, $calls);
        $this->assertSame($level, DB::transactionLevel());
        $this->assertDatabaseHas('clinic_branches', ['id' => $branch, 'name' => 'Committed synthetic write']);
    }

    public function test_consumer_failure_rolls_back_and_does_not_retry_the_callback(): void
    {
        $this->assertTrue(method_exists(WorkspaceAccess::class, 'run'));
        $this->owner();
        $clinic = $this->organisation('Rollback');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user);
        $access = app(WorkspaceAccess::class);
        $calls = 0;
        try {
            $access->run($access->resolve($user->id, $id), 'scheduling.write', function ($current) use (&$calls) {
                $calls++;
                DB::table('clinic_branches')->where('id', $current->branchId)->update(['name' => 'Must roll back']);
                throw new \RuntimeException('Synthetic consumer failure');
            });
            $this->fail('The consumer failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic consumer failure', $exception->getMessage());
        }
        $this->assertSame(1, $calls);
        $this->assertDatabaseHas('clinic_branches', ['id' => $branch, 'name' => 'Synthetic main']);
    }

    public function test_revoked_context_never_enters_a_consumer_callback(): void
    {
        $this->assertTrue(method_exists(WorkspaceAccess::class, 'run'));
        $this->owner();
        $clinic = $this->organisation('Revoke');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user);
        $access = app(WorkspaceAccess::class);
        $context = $access->resolve($user->id, $id);
        $this->deleteJson('/tenancy/v1/organisations/'.$clinic.'/memberships/'.$id, ['version' => 1])->assertOk();
        $calls = 0;
        try {
            $access->run($context, 'scheduling.write', function () use (&$calls) {
                $calls++;
            });
            $this->fail('A revoked context must be rejected.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame(0, $calls);
    }

    public function test_a_finance_membership_cannot_enter_a_scheduling_consumer(): void
    {
        $this->assertTrue(method_exists(WorkspaceAccess::class, 'run'));
        $this->owner();
        $clinic = $this->organisation('Separation');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user, 'accountant');
        $access = app(WorkspaceAccess::class);
        $calls = 0;
        try {
            $access->run($access->resolve($user->id, $id), 'scheduling.write', function () use (&$calls) {
                $calls++;
            });
            $this->fail('Wrong-domain permission must be rejected.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, $calls);
    }

    public function test_expiry_during_consumer_execution_rolls_back_the_write(): void
    {
        $this->assertTrue(method_exists(WorkspaceAccess::class, 'run'));
        $this->owner();
        $clinic = $this->organisation('Expiry');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user, 'receptionist', now()->addMinute()->toIso8601String());
        $access = app(WorkspaceAccess::class);
        try {
            $access->run($access->resolve($user->id, $id), 'scheduling.write', function ($current) {
                DB::table('clinic_branches')->where('id', $current->branchId)->update(['name' => 'Expired write']);
                $this->travel(61)->seconds();
            });
            $this->fail('Expiry must be rechecked before returning from the envelope.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        } finally {
            $this->travelBack();
        }
        $this->assertDatabaseHas('clinic_branches', ['id' => $branch, 'name' => 'Synthetic main']);
    }

    public function test_reserved_demo_identity_cannot_use_a_preexisting_workspace_membership(): void
    {
        $this->owner();
        $clinic = $this->organisation('Demo isolation');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user);
        DB::table('users')->where('id', $user->id)->update(['email' => 'demo-clinic@royadarman.invalid']);
        $this->actingAs($user->fresh())->postJson('/tenancy/v1/workspace', ['membership_id' => $id])->assertNotFound();
        $this->getJson('/tenancy/v1/workspaces')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_synthetic_clinic_cannot_use_a_preexisting_workspace_membership(): void
    {
        $this->owner();
        $clinic = $this->organisation('Demo clinic');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user);
        DB::table('clinics')->where('id', $clinic)->update(['synthetic_demo_key' => PanelDemoRegistry::CLINIC_DEMO_KEY]);
        $this->actingAs($user)->postJson('/tenancy/v1/workspace', ['membership_id' => $id])->assertNotFound();
        $this->getJson('/tenancy/v1/workspaces')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_populated_migration_rollback_refuses_without_deleting_tenant_records(): void
    {
        $this->owner();
        $clinic = $this->organisation('Retained records');
        $branch = $this->branch($clinic, 'main');
        $migration = require database_path('migrations/2026_10_04_000100_create_tenancy_foundation_tables.php');
        try {
            $migration->down();
            $this->fail('Populated schema rollback must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Refusing destructive tenancy rollback', $exception->getMessage());
        }
        $this->assertDatabaseHas('clinic_branches', ['id' => $branch]);
        $this->assertDatabaseCount('workspace_memberships', 1);
    }

    public function test_demo_session_cannot_select_a_real_workspace(): void
    {
        $this->owner();
        $clinic = $this->organisation('Real clinic');
        $branch = $this->branch($clinic, 'main');
        $id = DB::table('workspace_memberships')->where('branch_id', $branch)->value('id');
        $this->withSession(['panel_demo' => true]);
        $this->postJson('/tenancy/v1/workspace', ['membership_id' => $id])->assertForbidden();
        // Existing demo middleware invalidates this stale session; its next request is a guest.
        $this->getJson('/tenancy/v1/workspaces')->assertUnauthorized();
    }

    public function test_unknown_account_role_cannot_resolve_a_valid_membership(): void
    {
        $this->owner();
        $clinic = $this->organisation('Known roles only');
        $branch = $this->branch($clinic, 'main');
        $user = User::factory()->create();
        $id = $this->member($clinic, $branch, $user);
        $access = app(WorkspaceAccess::class);
        $context = $access->resolve($user->id, $id);
        DB::table('users')->where('id', $user->id)->update(['role' => 'not_an_account_role']);
        $this->assertFalse($access->allows($context, 'scheduling.write'));
        $calls = 0;
        try {
            $access->run($context, 'scheduling.write', function () use (&$calls) {
                $calls++;
            });
            $this->fail('Unknown account role must not enter the consumer.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertSame(0, $calls);
    }
}
