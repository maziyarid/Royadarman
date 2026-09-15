<?php

namespace App\Support;

use App\Domain\Identity\Enums\UserRole;
use Illuminate\Http\Request;

final class WorkspaceView
{
    /**
     * Shared Blade data for authenticated workspace shells.
     *
     * @return array{panelKey: string, isDemo: bool, canManageMarketing: bool, navActive: string, locale: string, role: UserRole}
     */
    public static function data(Request $request, string $navActive): array
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);

        $role = $user->role;
        $isDemo = (bool) $request->session()->get('panel_demo', false);
        $panelKey = match ($role) {
            UserRole::Patient => 'patient',
            UserRole::Coordinator => 'coordinator',
            UserRole::Clinician => 'clinician',
            UserRole::ClinicRepresentative => 'clinic_rep',
            UserRole::Owner => 'owner',
            UserRole::TechnicalAdministrator => 'tech_admin',
        };

        return [
            'panelKey' => $panelKey,
            'isDemo' => $isDemo,
            'canManageMarketing' => ! $isDemo && $role === UserRole::Owner,
            'navActive' => $navActive,
            'locale' => app()->getLocale(),
            'role' => $role,
        ];
    }
}
