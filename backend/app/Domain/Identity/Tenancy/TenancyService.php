<?php

namespace App\Domain\Identity\Tenancy;

use App\Models\AuditEvent;
use App\Support\PanelDemoRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenancyService
{
    public function __construct(private readonly WorkspaceAccess $access) {}

    public function createOrganisation(int $actorId, string $clinicId, string $displayName): Organisation
    {
        abort_unless(trim($displayName) !== '' && mb_strlen($displayName) <= 160, 422);

        return DB::transaction(function () use ($actorId, $clinicId, $displayName): Organisation {
            $this->clinic($clinicId);
            $this->owner($actorId);
            $existing = Organisation::query()->whereKey($clinicId)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->is_active && $existing->display_name === $displayName, 409);

                return $existing;
            }
            $organisation = Organisation::query()->create(['clinic_id' => $clinicId, 'display_name' => $displayName,
                'created_by_user_id' => $actorId, 'is_active' => true]);
            $this->audit($actorId, 'tenancy.organisation.created', $clinicId, $clinicId);

            return $organisation;
        }, 3);
    }

    public function createBranch(int $actorId, string $clinicId, string $code, string $name): ClinicBranch
    {
        abort_unless(preg_match('/\A[a-z0-9][a-z0-9_-]{0,39}\z/D', $code) === 1 && trim($name) !== '' && mb_strlen($name) <= 160, 422);

        return DB::transaction(function () use ($actorId, $clinicId, $code, $name): ClinicBranch {
            $this->organisation($clinicId);
            $existing = ClinicBranch::query()->where('clinic_id', $clinicId)->where('code', $code)->lockForUpdate()->first();
            $this->owner($actorId);
            if ($existing) {
                abort_unless($existing->is_active && $existing->name === $name, 409);

                return $existing;
            }
            $branch = ClinicBranch::query()->create(['clinic_id' => $clinicId, 'code' => $code, 'name' => $name, 'is_active' => true]);
            $membership = WorkspaceMembership::query()->create(['clinic_id' => $clinicId, 'branch_id' => $branch->id,
                'user_id' => $actorId, 'workspace_role' => 'owner', 'active_from' => now(), 'created_by_user_id' => $actorId, 'version' => 1]);
            $this->audit($actorId, 'tenancy.branch.created', $branch->id, $clinicId, $branch->id);
            $this->audit($actorId, 'tenancy.membership.granted', $membership->id, $clinicId, $branch->id, 1);

            return $branch;
        }, 3);
    }

    public function assign(int $actorId, string $clinicId, string $branchId, int $userId, string $role, ?CarbonImmutable $until = null): WorkspaceMembership
    {
        abort_unless(in_array($role, WorkspaceAccess::ROLES, true), 422);
        abort_if($until !== null && ! $until->isFuture(), 422);

        return DB::transaction(function () use ($actorId, $clinicId, $branchId, $userId, $role, $until): WorkspaceMembership {
            // The clinic first-lock serialises consumers, duplicate grants and revocations.
            $this->organisation($clinicId);
            abort_unless(ClinicBranch::query()->where('clinic_id', $clinicId)->whereKey($branchId)->where('is_active', true)->lockForUpdate()->first(), 404);
            $this->lockUsers([$actorId, $userId]);
            $this->owner($actorId);
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first(['id', 'role', 'is_active', 'email']);
            abort_unless($user && $user->is_active && ! $this->demo((string) $user->email), 422);
            abort_if($role === 'owner' && ($user->role !== 'owner' || $until !== null), 422);
            abort_if($role === 'developer' && $user->role !== 'tech_admin', 422);
            abort_if($role === 'dentist' && ($user->role !== 'clinician' || ! $this->access->credentialCurrent($userId, true)), 422);
            $existing = WorkspaceMembership::query()->where('branch_id', $branchId)->where('user_id', $userId)->where('workspace_role', $role)->lockForUpdate()->first();
            if ($existing) {
                $sameExpiry = $existing->active_until?->utc()->format('Y-m-d H:i:s') === $until?->utc()->format('Y-m-d H:i:s');
                abort_if($existing->revoked_at !== null || ($existing->active_until !== null && ! $existing->active_until->isFuture()) || ! $sameExpiry, 409);

                return $existing; // A duplicate request never reactivates revoked/expired authority.
            }
            $membership = WorkspaceMembership::query()->create(['clinic_id' => $clinicId, 'branch_id' => $branchId,
                'user_id' => $userId, 'workspace_role' => $role, 'active_from' => now(), 'active_until' => $until?->utc(),
                'created_by_user_id' => $actorId, 'version' => 1]);
            $this->audit($actorId, 'tenancy.membership.granted', $membership->id, $clinicId, $branchId, 1);

            return $membership;
        }, 3);
    }

    public function revoke(int $actorId, string $clinicId, string $membershipId, int $expectedVersion): WorkspaceMembership
    {
        return DB::transaction(function () use ($actorId, $clinicId, $membershipId, $expectedVersion): WorkspaceMembership {
            $this->organisation($clinicId);
            $hint = WorkspaceMembership::query()->where('clinic_id', $clinicId)->whereKey($membershipId)->first(['branch_id']);
            abort_unless($hint, 404);
            abort_unless(ClinicBranch::query()->where('clinic_id', $clinicId)->whereKey($hint->branch_id)->lockForUpdate()->first(), 404);
            // Candidate owner rows are locked under the clinic mutex before locking all their users in ID order.
            // This discovery is not authority: every status is rechecked with current reads below.
            $ownerRows = DB::table('workspace_memberships')->where('clinic_id', $clinicId)->where('branch_id', $hint->branch_id)
                ->where('workspace_role', 'owner')->orderBy('id')->lockForUpdate()->get(['user_id']);
            $this->lockUsers([$actorId, ...$ownerRows->pluck('user_id')->map(fn ($id) => (int) $id)->all()]);
            $this->owner($actorId);
            $membership = WorkspaceMembership::query()->where('clinic_id', $clinicId)->whereKey($membershipId)->lockForUpdate()->first();
            abort_unless($membership && $membership->branch_id === $hint->branch_id, 404);
            abort_unless($membership->version === $expectedVersion, 409);
            if ($membership->revoked_at !== null) {
                return $membership;
            }
            if ($membership->workspace_role === 'owner') {
                $other = DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
                    ->where('m.clinic_id', $clinicId)->where('m.branch_id', $membership->branch_id)
                    ->where('m.id', '!=', $membershipId)->where('m.workspace_role', 'owner')->where('u.role', 'owner')
                    ->where('u.is_active', true)->whereNull('m.revoked_at')->where('m.active_from', '<=', now())
                    ->where(fn ($query) => $query->whereNull('m.active_until')->orWhere('m.active_until', '>', now()))
                    ->orderBy('m.id')->lockForUpdate()->first(['m.id']);
                abort_unless($other, 409, 'The last active branch owner cannot be revoked.');
            }
            $membership->update(['revoked_at' => now(), 'revoked_by_user_id' => $actorId, 'version' => $membership->version + 1]);
            $this->audit($actorId, 'tenancy.membership.revoked', $membershipId, $clinicId, $membership->branch_id, $membership->version);

            return $membership;
        }, 3);
    }

    /** @param list<int> $ids */
    private function lockUsers(array $ids): void
    {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            DB::table('users')->where('id', $id)->lockForUpdate()->first(['id']);
        }
    }

    private function owner(int $actorId): void
    {
        $user = DB::table('users')->where('id', $actorId)->lockForUpdate()->first(['role', 'is_active', 'email']);
        abort_unless($user && $user->is_active && $user->role === 'owner' && ! $this->demo((string) $user->email), 403);
    }

    private function clinic(string $clinicId): void
    {
        $clinic = DB::table('clinics')->where('id', $clinicId)->lockForUpdate()->first(['is_active', 'synthetic_demo_key']);
        abort_unless($clinic && $clinic->is_active && empty($clinic->synthetic_demo_key), 404);
    }

    private function organisation(string $clinicId): void
    {
        $this->clinic($clinicId);
        $organisation = Organisation::query()->whereKey($clinicId)->lockForUpdate()->first();
        abort_unless($organisation && $organisation->is_active, 404);
    }

    private function demo(string $email): bool
    {
        return in_array($email, array_column(PanelDemoRegistry::identities(), 'email'), true);
    }

    private function audit(int $actorId, string $action, string $resourceId, string $clinicId, ?string $branchId = null, ?int $version = null): void
    {
        AuditEvent::query()->create(['actor_user_id' => $actorId, 'action' => $action, 'resource_type' => 'tenancy',
            'resource_id' => $resourceId, 'result' => 'allowed', 'context' => ['clinic_id' => $clinicId, 'branch_id' => $branchId, 'version' => $version],
            'correlation_id' => (string) Str::ulid(), 'created_at' => now()]);
    }
}
