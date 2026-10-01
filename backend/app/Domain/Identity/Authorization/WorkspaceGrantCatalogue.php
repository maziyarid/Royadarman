<?php

namespace App\Domain\Identity\Authorization;

use App\Domain\Identity\Enums\UserRole;
use DateTimeImmutable;
use Exception;

/**
 * Proposed workspace ids only. This class is not called by a controller,
 * policy, route or job. evaluate() never allows an action.
 */
final class WorkspaceGrantCatalogue
{
    public const STATUS = 'proposed_not_activated';

    public const ACTION_CLINICAL_SIGN = 'clinical_sign';

    public const ACTION_CLINICAL_READ = 'clinical_read';

    public const ACTION_CLINICAL_DRAFT = 'clinical_draft';

    public const ACTION_SUPPORT_CONVERSATION_READ = 'support_conversation_read';

    public const ACTION_FINANCE_APPROVE = 'finance_approve';

    public const ACTION_SCHEDULING_WRITE = 'scheduling_write';

    public const ACTION_TENANT_ADMIN = 'tenant_admin';

    public const ACTION_DIAGNOSTIC_READ = 'diagnostic_read';

    /**
     * Roadmap section 2, 30 September 2026. Identifiers, not grants.
     *
     * @return list<string>
     */
    public static function workspaceIds(): array
    {
        return [
            'owner',
            'developer',
            'superadmin',
            'supervisor',
            'receptionist',
            'accountant',
            'customer_support',
            'treatment_specialist',
            'clinic_manager',
            'dentist',
            'clinical_staff',
            'patient',
        ];
    }

    /**
     * @return list<string>
     */
    public static function actions(): array
    {
        return [
            self::ACTION_CLINICAL_SIGN,
            self::ACTION_CLINICAL_READ,
            self::ACTION_CLINICAL_DRAFT,
            self::ACTION_SUPPORT_CONVERSATION_READ,
            self::ACTION_FINANCE_APPROVE,
            self::ACTION_SCHEDULING_WRITE,
            self::ACTION_TENANT_ADMIN,
            self::ACTION_DIAGNOSTIC_READ,
        ];
    }

    /**
     * Readers of users.role stay on these six values.
     *
     * @return list<string>
     */
    public static function preservedAccountRoles(): array
    {
        return array_map(
            static fn (UserRole $role): string => $role->value,
            UserRole::cases(),
        );
    }

    /**
     * Pairs the roadmap writes with a slash against the current enum.
     * Not an authority crosswalk. coordinator, clinic_rep and tech_admin
     * are intentionally absent.
     *
     * @return array<string, string>
     */
    public static function roadmapSlashPairs(): array
    {
        return [
            'owner' => UserRole::Owner->value,
            'dentist' => UserRole::Clinician->value,
            'patient' => UserRole::Patient->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function proposedScopeKinds(): array
    {
        return ['personal', 'organisation', 'clinic', 'branch', 'team', 'assignment'];
    }

    /**
     * Tables that exist today and can carry a scope id. Branch, organisation
     * and team tables do not exist. Assignment is a case assignment, not a
     * workspace-grant scope.
     *
     * @return list<string>
     */
    public static function schemaBackedScopeKinds(): array
    {
        return ['personal', 'clinic'];
    }

    public static function titleGrantsClinicalSigning(string $workspaceId): bool
    {
        return false;
    }

    public static function evaluate(
        string $workspaceId,
        string $action,
        bool $revoked,
        ?string $expiresAt,
        string $now,
    ): MembershipPermission {
        if (! in_array($workspaceId, self::workspaceIds(), true)) {
            return new MembershipPermission(false, 'unknown_workspace');
        }

        if (! in_array($action, self::actions(), true)) {
            return new MembershipPermission(false, 'unknown_action');
        }

        if ($revoked) {
            return new MembershipPermission(false, 'grant_revoked');
        }

        $expiry = self::expiryReason($expiresAt, $now);
        if ($expiry !== null) {
            return new MembershipPermission(false, $expiry);
        }

        return new MembershipPermission(false, 'grant_not_activated');
    }

    private static function expiryReason(?string $expiresAt, string $now): ?string
    {
        if ($expiresAt === null || $expiresAt === '') {
            return null;
        }

        if (! self::isAbsoluteTimestamp($expiresAt) || ! self::isAbsoluteTimestamp($now)) {
            return 'invalid_expiry';
        }

        try {
            $expiry = new DateTimeImmutable($expiresAt);
            $moment = new DateTimeImmutable($now);
        } catch (Exception) {
            return 'invalid_expiry';
        }

        return $expiry <= $moment ? 'grant_expired' : null;
    }

    private static function isAbsoluteTimestamp(string $value): bool
    {
        return preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',
            $value,
        ) === 1;
    }
}
