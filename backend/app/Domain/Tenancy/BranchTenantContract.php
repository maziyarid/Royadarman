<?php

namespace App\Domain\Tenancy;

use App\Domain\Identity\Authorization\MembershipPermission;

/**
 * Proposed tenant shape only. No method allows a migration or a write.
 * Branch rows cannot be enforced: the branches table is not in the schema.
 */
final class BranchTenantContract
{
    public const STATUS = 'proposed_not_migrated';

    public const TENANT_GRAIN = 'clinic';

    /**
     * @return list<string>
     */
    public static function proposedBranchPrimaryKey(): array
    {
        return ['clinic_id', 'id'];
    }

    /**
     * @return list<string>
     */
    public static function proposedBranchChildForeignKey(): array
    {
        return ['clinic_id', 'branch_id'];
    }

    public static function createDatabaseDenialExpandsPrivileges(): bool
    {
        return false;
    }

    /**
     * No agreed recovery objective. Null is the absence of a decision, not zero.
     *
     * @return array{rpo: string, rto: string}|null
     */
    public static function acceptedRecoveryObjectives(): ?array
    {
        return null;
    }

    public static function migrationGate(bool $freshBackupVerified, bool $separateRestoreTargetProven): MembershipPermission
    {
        if (! $freshBackupVerified) {
            return new MembershipPermission(false, 'fresh_backup_not_verified');
        }

        if (! $separateRestoreTargetProven) {
            return new MembershipPermission(false, 'restore_target_not_proven');
        }

        return new MembershipPermission(false, 'migration_not_in_this_contract');
    }

    public static function scope(?string $clinicId, ?string $branchId): MembershipPermission
    {
        $clinic = $clinicId !== null && $clinicId !== '';
        $branch = $branchId !== null && $branchId !== '';

        if ($branch && ! $clinic) {
            return new MembershipPermission(false, 'branch_without_clinic');
        }

        if ($branch && $clinic) {
            return new MembershipPermission(false, 'branch_table_absent');
        }

        if ($clinic) {
            return new MembershipPermission(false, 'clinic_scope_not_a_migration');
        }

        return new MembershipPermission(false, 'tenant_scope_missing');
    }

    public static function proposedCaseColumn(string $column): MembershipPermission
    {
        if ($column === 'clinic_id' || $column === 'branch_id') {
            return new MembershipPermission(false, 'case_is_not_single_clinic_owned');
        }

        return new MembershipPermission(false, 'case_column_not_in_this_contract');
    }

    public static function grantFollowsProposal(
        string $proposalClinicId,
        string $grantClinicId,
        string $proposalCaseId,
        string $grantCaseId,
    ): MembershipPermission {
        if ($proposalClinicId === '' || $grantClinicId === '' || $proposalCaseId === '' || $grantCaseId === '') {
            return new MembershipPermission(false, 'linkage_incomplete');
        }

        if ($proposalClinicId !== $grantClinicId || $proposalCaseId !== $grantCaseId) {
            return new MembershipPermission(false, 'grant_proposal_mismatch');
        }

        return new MembershipPermission(false, 'linkage_matches_but_not_activated');
    }
}
