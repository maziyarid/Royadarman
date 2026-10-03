<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\UserRole;

/**
 * Central action-capability map for staff roles. Policies and controllers must
 * consult this map instead of scattering role comparisons, so adding a role
 * updates one contract and denial stays the default everywhere else.
 */
final class StaffCapabilities
{
    /** @var array<string, list<UserRole>> */
    private const CAPABILITIES = [
        'support.view' => [UserRole::Coordinator, UserRole::CustomerSupport, UserRole::Owner, UserRole::Superadmin, UserRole::TechnicalAdministrator],
        'support.reply' => [UserRole::Coordinator, UserRole::CustomerSupport],
        'support.assign' => [UserRole::Coordinator, UserRole::Supervisor, UserRole::Owner, UserRole::Superadmin],
        'support.internal_note' => [UserRole::Coordinator, UserRole::CustomerSupport, UserRole::Owner, UserRole::Superadmin],
        'support.change_status' => [UserRole::Coordinator, UserRole::CustomerSupport, UserRole::Owner, UserRole::Superadmin],
        'coordination.assign' => [UserRole::Coordinator, UserRole::Supervisor, UserRole::Owner, UserRole::Superadmin],
        'network.manage' => [UserRole::Owner, UserRole::Superadmin],
        'cms.manage' => [UserRole::Owner, UserRole::Superadmin, UserRole::TechnicalAdministrator],
        'integration.manage' => [UserRole::Owner, UserRole::Superadmin, UserRole::TechnicalAdministrator],
        'finance.view' => [UserRole::Accountant, UserRole::Owner, UserRole::Superadmin],
        'reception.schedule' => [UserRole::Receptionist, UserRole::ClinicManager, UserRole::ClinicRepresentative],
        'diagnostics.view' => [UserRole::Developer, UserRole::TechnicalAdministrator],
    ];

    public static function can(UserRole $role, string $capability): bool
    {
        $roles = self::CAPABILITIES[$capability] ?? null;

        if ($roles === null) {
            return false;
        }

        return in_array($role, $roles, true);
    }

    /** @return list<string> */
    public static function capabilitiesFor(UserRole $role): array
    {
        $held = [];
        foreach (array_keys(self::CAPABILITIES) as $capability) {
            if (in_array($role, self::CAPABILITIES[$capability], true)) {
                $held[] = $capability;
            }
        }

        return $held;
    }
}
