<?php

namespace App\Support;

use App\Domain\Identity\Enums\UserRole;

final class PanelDemoRegistry
{
    /**
     * Immutable discriminator for the single synthetic demo clinic.
     * Never look the demo clinic up by display name.
     */
    public const CLINIC_DEMO_KEY = 'royadarman.panel-demo.clinic';

    public const CLINIC_DISPLAY_NAME = 'TEST Demo Clinic';

    public const CLINIC_AREA_CODE = 'test-demo';

    public const CASE_REFERENCE_PREFIX = 'TEST-DEMO-';

    public const OPG_CASE_REFERENCE = 'TEST-DEMO-OPG-001';

    public const REFERRAL_CASE_REFERENCE = 'TEST-DEMO-REF-001';

    public const HOME_CASE_REFERENCE = 'TEST-DEMO-HOME-001';

    public const DOCUMENT_STORAGE_KEY = 'panel-demo/synthetic-no-image';

    public const SUPPORT_SUBJECT = 'TEST synthetic coordination question';

    public const AUDIT_SEED_ACTION = 'demo.panel.seeded';

    public const AUDIT_RESOURCE_TYPE = 'panel_demo';

    /**
     * @return array<string, array{email: string, name: string, role: UserRole}>
     */
    public static function identities(): array
    {
        return [
            'admin' => [
                'email' => 'demo-owner@royadarman.invalid',
                'name' => 'TEST Demo Owner',
                'role' => UserRole::Owner,
            ],
            'client' => [
                'email' => 'demo-patient@royadarman.invalid',
                'name' => 'TEST Demo Patient',
                'role' => UserRole::Patient,
            ],
            'clinic' => [
                'email' => 'demo-clinic@royadarman.invalid',
                'name' => 'TEST Demo Clinic Representative',
                'role' => UserRole::ClinicRepresentative,
            ],
            'coordinator' => [
                'email' => 'demo-coordinator@royadarman.invalid',
                'name' => 'TEST Demo Coordinator',
                'role' => UserRole::Coordinator,
            ],
            'clinician' => [
                'email' => 'demo-clinician@royadarman.invalid',
                'name' => 'TEST Demo Clinician',
                'role' => UserRole::Clinician,
            ],
            'tech' => [
                'email' => 'demo-tech@royadarman.invalid',
                'name' => 'TEST Demo Technical Administrator',
                'role' => UserRole::TechnicalAdministrator,
            ],
        ];
    }

    /** @return list<string> */
    public static function aliases(): array
    {
        return array_keys(self::identities());
    }

    /** @return array{email: string, name: string, role: UserRole}|null */
    public static function identity(string $alias): ?array
    {
        return self::identities()[$alias] ?? null;
    }

    public static function isDemoCaseReference(?string $reference): bool
    {
        return is_string($reference) && str_starts_with($reference, self::CASE_REFERENCE_PREFIX);
    }

    /** @return list<string> */
    public static function caseReferences(): array
    {
        return [self::OPG_CASE_REFERENCE, self::REFERRAL_CASE_REFERENCE, self::HOME_CASE_REFERENCE];
    }
}
