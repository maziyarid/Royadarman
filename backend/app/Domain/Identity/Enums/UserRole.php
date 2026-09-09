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

    public function isStaff(): bool
    {
        return $this !== self::Patient;
    }
}

