<?php

namespace Tests\Unit;

use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Services\StaffCapabilities;
use PHPUnit\Framework\TestCase;

final class StaffCapabilitiesTest extends TestCase
{
    public function test_patient_holds_no_staff_capability(): void
    {
        foreach (UserRole::cases() as $role) {
            if ($role === UserRole::Patient) {
                $this->assertSame([], StaffCapabilities::capabilitiesFor($role));
            }
        }
    }

    public function test_unknown_capability_denies_every_role(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertFalse(StaffCapabilities::can($role, 'does.not.exist'));
        }
    }

    public function test_every_declared_capability_resolves_for_at_least_one_role(): void
    {
        $rolesWithCapabilities = [];

        foreach (UserRole::cases() as $role) {
            if (StaffCapabilities::capabilitiesFor($role) !== []) {
                $rolesWithCapabilities[] = $role;
            }
        }

        $this->assertNotEmpty($rolesWithCapabilities);
    }

    public function test_clinical_and_finance_boundaries_hold(): void
    {
        $this->assertSame([], StaffCapabilities::capabilitiesFor(UserRole::Clinician));
        $this->assertNotContains('finance.view', StaffCapabilities::capabilitiesFor(UserRole::Receptionist));
        $this->assertNotContains('cms.manage', StaffCapabilities::capabilitiesFor(UserRole::Accountant));
        $this->assertNotContains('network.manage', StaffCapabilities::capabilitiesFor(UserRole::Developer));
        $this->assertContains('finance.view', StaffCapabilities::capabilitiesFor(UserRole::Accountant));
        $this->assertContains('diagnostics.view', StaffCapabilities::capabilitiesFor(UserRole::Developer));
    }

    public function test_dashboard_families_are_existing_dashboard_roles(): void
    {
        $existing = [
            UserRole::Patient,
            UserRole::Coordinator,
            UserRole::Clinician,
            UserRole::ClinicRepresentative,
            UserRole::Owner,
            UserRole::TechnicalAdministrator,
        ];

        foreach (UserRole::cases() as $role) {
            $this->assertContains($role->dashboardFamily(), $existing, $role->value);
        }
    }

    public function test_new_roles_are_staff_and_privilege_helpers_are_consistent(): void
    {
        $this->assertTrue(UserRole::Superadmin->isStaff());
        $this->assertTrue(UserRole::Developer->isPrivileged());
        $this->assertTrue(UserRole::Superadmin->isPlatformAdministrator());
        $this->assertFalse(UserRole::Accountant->isPrivileged());
        $this->assertTrue(UserRole::Clinician->isClinicalSigner());
        $this->assertFalse(UserRole::Superadmin->isClinicalSigner());
        $this->assertFalse(UserRole::Patient->isStaff());
    }
}
