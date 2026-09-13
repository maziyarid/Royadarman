<?php

namespace App\Support;

use App\Domain\Identity\Enums\UserRole;

final class PanelDemoRegistry
{
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
}
