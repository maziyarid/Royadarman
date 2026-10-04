<?php

namespace App\Domain\Identity\Tenancy;

use App\Domain\Identity\Enums\UserRole;
use App\Support\PanelDemoRegistry;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class WorkspaceAccess
{
    public const SESSION_KEY = 'tenancy.workspace';

    public const ROLES = ['owner', 'developer', 'superadmin', 'supervisor', 'receptionist', 'accountant',
        'customer_support', 'treatment_specialist', 'clinic_manager', 'dentist', 'clinical_staff', 'patient'];

    public function resolve(int $actorId, string $membershipId, ?string $clinicId = null, ?string $branchId = null, ?int $version = null): WorkspaceContext
    {
        $row = $this->currentQuery($actorId)->where('m.id', $membershipId)->first([
            'm.id', 'm.clinic_id', 'm.branch_id', 'm.workspace_role', 'm.version', 'u.role as account_role',
        ]);
        abort_unless($row && UserRole::tryFrom((string) $row->account_role) !== null
            && in_array($row->workspace_role, self::ROLES, true), 404);
        abort_if(($clinicId !== null && $clinicId !== $row->clinic_id)
            || ($branchId !== null && $branchId !== $row->branch_id)
            || ($version !== null && $version !== (int) $row->version), 404);
        abort_if($row->workspace_role === 'owner' && $row->account_role !== 'owner', 404);
        abort_if($row->workspace_role === 'developer' && $row->account_role !== 'tech_admin', 404);
        if ($row->workspace_role === 'dentist') {
            abort_unless($row->account_role === 'clinician' && $this->credentialCurrent($actorId), 404);
        }

        return new WorkspaceContext($actorId, $row->clinic_id, $row->branch_id, $row->id, $row->workspace_role, (int) $row->version);
    }

    /** Re-read every time, including when the caller supplies a previously valid context. */
    public function authorize(WorkspaceContext $context, string $permission): WorkspaceContext
    {
        $fresh = $this->resolve($context->actorId, $context->membershipId, $context->clinicId, $context->branchId, $context->membershipVersion);
        $this->requirePermission($fresh, $permission);

        return $fresh;
    }

    private function requirePermission(WorkspaceContext $fresh, string $permission): void
    {
        $roles = match ($permission) {
            'workspace.read' => self::ROLES,
            'scheduling.read' => ['owner', 'superadmin', 'supervisor', 'receptionist', 'clinic_manager', 'dentist', 'clinical_staff'],
            'scheduling.write' => ['owner', 'superadmin', 'receptionist', 'clinic_manager'],
            'finance.read' => ['owner', 'superadmin', 'accountant', 'clinic_manager'],
            'diagnostics.read_redacted' => ['developer'],
            default => [], // Clinical authorship, consent, exceptional access and finance approval remain domain gates.
        };
        abort_unless(in_array($fresh->workspaceRole, $roles, true), 403);
    }

    public function allows(WorkspaceContext $context, string $permission): bool
    {
        try {
            $this->authorize($context, $permission);

            return true;
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [403, 404], true)) {
                throw $exception;
            }

            return false;
        }
    }

    /** @return list<WorkspaceContext> */
    public function memberships(int $actorId): array
    {
        $result = [];
        foreach ($this->currentQuery($actorId)->orderBy('m.id')->pluck('m.id') as $id) {
            try {
                $result[] = $this->resolve($actorId, $id);
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 404) {
                    throw $exception;
                }
            }
        }

        return $result;
    }

    /**
     * The callback must use this connection, scoped domain queries and no external effects.
     * Enter before acquiring domain locks. Nested transactions retain locks until the
     * outer commit; locking reads here do not reuse its repeatable-read snapshot.
     * No automatic retry: a callback must never be unknowingly executed twice.
     *
     * @template T
     *
     * @param  Closure(WorkspaceContext): T  $operation
     * @return T
     */
    public function run(WorkspaceContext $context, string $permission, Closure $operation): mixed
    {
        return DB::transaction(function () use ($context, $permission, $operation): mixed {
            $fresh = $this->lockedResolve($context);
            $this->requirePermission($fresh, $permission);
            $result = $operation($fresh);
            // Time-based expiry can change while row locks are held.
            $this->requirePermission($this->lockedResolve($fresh), $permission);

            return $result;
        }, 1);
    }

    public function credentialCurrent(int $actorId, bool $lock = false): bool
    {
        $query = DB::table('practitioners')->where('user_id', $actorId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $credential = $query->first(['credential_status', 'expires_at']);

        return $credential && $credential->credential_status === 'verified'
            && ($credential->expires_at === null || CarbonImmutable::parse($credential->expires_at, 'UTC')->isFuture());
    }

    private function lockedResolve(WorkspaceContext $context): WorkspaceContext
    {
        // Clinic is the common first lock for every tenancy mutation and consumer.
        $clinic = DB::table('clinics')->where('id', $context->clinicId)->lockForUpdate()->first(['is_active', 'synthetic_demo_key']);
        abort_unless($clinic && $clinic->is_active && empty($clinic->synthetic_demo_key), 404);
        $organisation = DB::table('organisations')->where('clinic_id', $context->clinicId)->lockForUpdate()->first(['is_active']);
        abort_unless($organisation && $organisation->is_active, 404);
        $branch = DB::table('clinic_branches')->where('clinic_id', $context->clinicId)->where('id', $context->branchId)
            ->lockForUpdate()->first(['is_active']);
        abort_unless($branch && $branch->is_active, 404);
        $user = DB::table('users')->where('id', $context->actorId)->lockForUpdate()->first(['role', 'is_active', 'email']);
        abort_unless($user && $user->is_active && UserRole::tryFrom((string) $user->role) !== null
            && ! in_array($user->email, array_column(PanelDemoRegistry::identities(), 'email'), true), 404);
        $membership = DB::table('workspace_memberships')->where('id', $context->membershipId)
            ->where('clinic_id', $context->clinicId)->where('branch_id', $context->branchId)->where('user_id', $context->actorId)
            ->lockForUpdate()->first(['id', 'workspace_role', 'version', 'active_from', 'active_until', 'revoked_at']);
        abort_unless($membership && $membership->revoked_at === null
            && (int) $membership->version === $context->membershipVersion
            && in_array($membership->workspace_role, self::ROLES, true)
            && CarbonImmutable::parse($membership->active_from, 'UTC')->lessThanOrEqualTo(now())
            && ($membership->active_until === null || CarbonImmutable::parse($membership->active_until, 'UTC')->isFuture()), 404);
        abort_if($membership->workspace_role === 'owner' && $user->role !== 'owner', 404);
        abort_if($membership->workspace_role === 'developer' && $user->role !== 'tech_admin', 404);
        if ($membership->workspace_role === 'dentist') {
            abort_unless($user->role === 'clinician' && $this->credentialCurrent($context->actorId, true), 404);
        }

        return new WorkspaceContext($context->actorId, $context->clinicId, $context->branchId,
            $membership->id, $membership->workspace_role, (int) $membership->version);
    }

    private function currentQuery(int $actorId): Builder
    {
        return DB::table('workspace_memberships as m')->join('users as u', 'u.id', '=', 'm.user_id')
            ->join('clinic_branches as b', function ($join): void {
                $join->on('b.id', '=', 'm.branch_id')->on('b.clinic_id', '=', 'm.clinic_id');
            })->join('organisations as o', 'o.clinic_id', '=', 'm.clinic_id')
            ->join('clinics as c', 'c.id', '=', 'm.clinic_id')
            ->where('m.user_id', $actorId)->where('u.is_active', true)->where('b.is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('u.email')->orWhereNotIn('u.email', array_column(PanelDemoRegistry::identities(), 'email')))
            ->where(fn (Builder $query) => $query->whereNull('c.synthetic_demo_key')->orWhere('c.synthetic_demo_key', ''))
            ->where('o.is_active', true)->where('c.is_active', true)->whereNull('m.revoked_at')
            ->where('m.active_from', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('m.active_until')->orWhere('m.active_until', '>', now()));
    }
}
