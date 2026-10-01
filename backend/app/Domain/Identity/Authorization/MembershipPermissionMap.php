<?php

namespace App\Domain\Identity\Authorization;

use App\Domain\Identity\Enums\UserRole;

final class MembershipPermissionMap
{
    public const ROLE_REVIEWER = 'reviewer';

    public const ROLE_CONTACT = 'contact';

    /**
     * @return list<string>
     */
    public static function knownMembershipRoles(): array
    {
        return [self::ROLE_REVIEWER, self::ROLE_CONTACT];
    }

    public static function assignMembership(
        ?UserRole $accountRole,
        string $membershipRole,
        bool $accountActive,
        bool $reviewerCredentialCurrent,
    ): MembershipPermission {
        if (! $accountActive) {
            return self::deny('account_inactive');
        }

        if (! in_array($membershipRole, self::knownMembershipRoles(), true)) {
            return self::deny('unknown_membership_role');
        }

        if (! $accountRole instanceof UserRole) {
            return self::deny('unknown_account_role');
        }

        if (! in_array($accountRole, [UserRole::Clinician, UserRole::ClinicRepresentative], true)) {
            return self::deny('account_role_cannot_join_clinic');
        }

        if ($membershipRole === self::ROLE_REVIEWER) {
            if ($accountRole !== UserRole::Clinician) {
                return self::deny('reviewer_requires_clinician');
            }

            if (! $reviewerCredentialCurrent) {
                return self::deny('reviewer_requires_current_credential');
            }

            return self::allow('assigned_reviewer');
        }

        return self::allow('assigned_contact');
    }

    public static function clinicalRecordFromMembership(
        ?UserRole $accountRole,
        ?string $membershipRole,
        bool $membershipActive,
        bool $reviewerCredentialCurrent,
    ): MembershipPermission {
        if (! $accountRole instanceof UserRole) {
            return self::deny('unknown_account_role');
        }

        if (in_array($accountRole, [
            UserRole::Patient,
            UserRole::Coordinator,
            UserRole::Owner,
            UserRole::TechnicalAdministrator,
        ], true)) {
            return self::deny('account_role_cannot_open_clinical_record');
        }

        if ($accountRole === UserRole::Clinician
            && $membershipRole === self::ROLE_REVIEWER
            && $membershipActive
            && $reviewerCredentialCurrent
        ) {
            return self::deny('membership_is_not_clinical_access');
        }

        return self::deny('membership_is_not_clinical_access');
    }

    public static function supportWorkspaceFromMembership(
        ?UserRole $accountRole,
        ?string $membershipRole,
        bool $membershipActive,
    ): MembershipPermission {
        if ($accountRole === UserRole::Coordinator
            && $membershipRole === self::ROLE_CONTACT
            && $membershipActive
        ) {
            return self::deny('membership_does_not_grant_support_workspace');
        }

        return self::deny('membership_does_not_grant_support_workspace');
    }

    private static function allow(string $reason): MembershipPermission
    {
        return new MembershipPermission(true, $reason);
    }

    private static function deny(string $reason): MembershipPermission
    {
        return new MembershipPermission(false, $reason);
    }
}
