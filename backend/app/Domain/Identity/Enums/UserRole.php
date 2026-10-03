<?php

namespace App\Domain\Identity\Enums;

enum UserRole: string
{
    case Patient = 'patient';
    case Coordinator = 'coordinator';
    case Clinician = 'clinician';
    case ClinicRepresentative = 'clinic_rep';
    case Owner = 'owner';
    case TechnicalAdministrator = 'tech_admin';
    case Superadmin = 'superadmin';
    case Developer = 'developer';
    case Supervisor = 'supervisor';
    case Receptionist = 'receptionist';
    case Accountant = 'accountant';
    case CustomerSupport = 'customer_support';
    case ClinicManager = 'clinic_manager';

    public function isStaff(): bool
    {
        return $this !== self::Patient;
    }

    public function isClinicalSigner(): bool
    {
        return $this === self::Clinician;
    }

    public function isPlatformAdministrator(): bool
    {
        return in_array($this, [self::Owner, self::Superadmin], true);
    }

    public function isPrivileged(): bool
    {
        return in_array($this, [self::Owner, self::Superadmin, self::TechnicalAdministrator, self::Developer], true);
    }

    /**
     * Dashboard family for rendering and metrics selection. New granular roles
     * reuse an existing dashboard family until their dedicated workspace ships.
     */
    public function dashboardFamily(): self
    {
        return match ($this) {
            self::Superadmin, self::ClinicManager, self::Receptionist => self::ClinicRepresentative,
            self::Accountant => self::Owner,
            self::CustomerSupport, self::Supervisor => self::Coordinator,
            self::Developer => self::TechnicalAdministrator,
            default => $this,
        };
    }
}
