<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\UserRole;
use App\Support\PanelDemoRegistry;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class NetworkAdminController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        $this->authorizeOwner($request);

        $q = trim((string) $request->query('q', ''));
        $clinics = DB::table('clinics')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('city', 'like', '%'.$q.'%')
                        ->orWhere('area_code', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name')
            ->get();
        $staff = DB::table('users')
            ->leftJoin('practitioners', 'practitioners.user_id', '=', 'users.id')
            ->whereIn('users.role', [UserRole::Coordinator->value, UserRole::Clinician->value, UserRole::ClinicRepresentative->value])
            ->orderBy('users.role')->orderBy('users.name')
            ->get([
                'users.id', 'users.name', 'users.email', 'users.role', 'users.locale', 'users.is_active',
                'practitioners.id as practitioner_id', 'practitioners.credential_status', 'practitioners.verified_at', 'practitioners.expires_at',
            ]);
        $memberships = DB::table('clinic_memberships')
            ->join('clinics', 'clinics.id', '=', 'clinic_memberships.clinic_id')
            ->join('users', 'users.id', '=', 'clinic_memberships.user_id')
            ->orderBy('clinics.name')->orderBy('users.name')
            ->get([
                'clinic_memberships.id', 'clinic_memberships.membership_role', 'clinic_memberships.active_from', 'clinic_memberships.active_until',
                'clinics.name as clinic_name', 'clinics.id as clinic_id', 'clinics.synthetic_demo_key as clinic_demo_key',
                'users.name as user_name', 'users.id as user_id', 'users.role as user_role',
            ]);

        return view('panel.network.index', [
            ...WorkspaceView::data($request, 'network'),
            'clinics' => $clinics,
            'staff' => $staff,
            'memberships' => $memberships,
            'locale' => $locale,
            'search' => $q,
            'demoEmails' => array_column(PanelDemoRegistry::identities(), 'email'),
        ]);
    }

    public function storeClinic(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', Rule::notIn([PanelDemoRegistry::CLINIC_DISPLAY_NAME])],
            'city' => ['required', 'string', 'max:80'],
            'area_code' => ['nullable', 'string', 'max:80'],
        ]);

        DB::table('clinics')->insert([
            'id' => (string) Str::ulid(),
            'name' => $data['name'],
            'city' => $data['city'],
            'area_code' => $data['area_code'] ?? null,
            'synthetic_demo_key' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('network.index', ['locale' => $locale])->with('status', __('network.status_messages.clinic_saved'));
    }

    public function updateClinic(Request $request, string $locale, string $clinic): RedirectResponse
    {
        $this->authorizeOwner($request);
        $existing = DB::table('clinics')->where('id', $clinic)->first();
        abort_unless($existing, 404);
        abort_if(! empty($existing->synthetic_demo_key), 403, 'The synthetic demo clinic cannot be mutated from network administration.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', Rule::notIn([PanelDemoRegistry::CLINIC_DISPLAY_NAME])],
            'city' => ['required', 'string', 'max:80'],
            'area_code' => ['nullable', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::table('clinics')->where('id', $clinic)->update([
            'name' => $data['name'],
            'city' => $data['city'],
            'area_code' => $data['area_code'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'updated_at' => now(),
        ]);

        return redirect()->route('network.index', ['locale' => $locale])->with('status', __('network.status_messages.clinic_saved'));
    }

    public function savePractitioner(Request $request, string $locale, int $user): RedirectResponse
    {
        $this->authorizeOwner($request);
        $staff = DB::table('users')->where('id', $user)->first(['id', 'role', 'is_active', 'email']);
        abort_unless($staff && $staff->role === UserRole::Clinician->value, 404);
        abort_if($this->isDemoIdentity((string) $staff->email), 403, 'Reserved demonstration identities cannot be mutated from network administration.');

        $data = $request->validate([
            'licence_number' => ['required', 'string', 'max:120'],
            'credential_status' => ['required', Rule::in(['pending', 'verified', 'revoked'])],
            'expires_at' => ['nullable', 'date'],
        ]);
        $licence = trim($data['licence_number']);
        $values = [
            'licence_number' => encrypt($licence),
            'licence_hash' => hash('sha256', mb_strtoupper($licence)),
            'credential_status' => $data['credential_status'],
            'verified_at' => $data['credential_status'] === 'verified' ? now() : null,
            'expires_at' => $data['expires_at'] ?? null,
            'updated_at' => now(),
        ];

        $existing = DB::table('practitioners')->where('user_id', $user)->first(['id']);
        if ($existing) {
            DB::table('practitioners')->where('id', $existing->id)->update($values);
        } else {
            DB::table('practitioners')->insert([
                'id' => (string) Str::ulid(),
                'user_id' => $user,
                ...$values,
                'created_at' => now(),
            ]);
        }

        return redirect()->route('network.index', ['locale' => $locale])->with('status', __('network.status_messages.credential_saved'));
    }

    public function storeMembership(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'clinic_id' => ['required', 'exists:clinics,id'],
            'user_id' => ['required', 'exists:users,id'],
            'membership_role' => ['required', Rule::in(['reviewer', 'contact'])],
            'active_until' => ['nullable', 'date', 'after:now'],
        ]);

        $clinic = DB::table('clinics')->where('id', $data['clinic_id'])->first(['id', 'synthetic_demo_key']);
        abort_unless($clinic, 404);
        abort_if(! empty($clinic->synthetic_demo_key), 403, 'The synthetic demo clinic cannot be mutated from network administration.');

        $staff = DB::table('users')->where('id', $data['user_id'])->first(['id', 'role', 'is_active', 'email']);
        abort_unless($staff && $staff->is_active, 422);
        abort_if($this->isDemoIdentity((string) $staff->email), 403, 'Reserved demonstration identities cannot be mutated from network administration.');
        if ($data['membership_role'] === 'reviewer') {
            abort_unless($staff->role === UserRole::Clinician->value, 422);
            $verified = DB::table('practitioners')
                ->where('user_id', $staff->id)
                ->where('credential_status', 'verified')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
            abort_unless($verified, 422);
        } else {
            abort_unless(in_array($staff->role, [UserRole::Clinician->value, UserRole::ClinicRepresentative->value], true), 422);
        }

        $existing = DB::table('clinic_memberships')
            ->where('clinic_id', $data['clinic_id'])
            ->where('user_id', $staff->id)
            ->first(['id']);
        $values = [
            'membership_role' => $data['membership_role'],
            'active_from' => now(),
            'active_until' => $data['active_until'] ?? null,
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('clinic_memberships')->where('id', $existing->id)->update($values);
        } else {
            DB::table('clinic_memberships')->insert([
                'id' => (string) Str::ulid(),
                'clinic_id' => $data['clinic_id'],
                'user_id' => $staff->id,
                ...$values,
                'created_at' => now(),
            ]);
        }

        return redirect()->route('network.index', ['locale' => $locale])->with('status', __('network.status_messages.membership_saved'));
    }

    public function revokeMembership(Request $request, string $locale, string $membership): RedirectResponse
    {
        $this->authorizeOwner($request);
        $row = DB::table('clinic_memberships')->where('id', $membership)->first(['id', 'clinic_id']);
        abort_unless($row, 404);
        $clinic = DB::table('clinics')->where('id', $row->clinic_id)->first(['synthetic_demo_key']);
        abort_if(! empty($clinic?->synthetic_demo_key), 403, 'The synthetic demo clinic cannot be mutated from network administration.');
        DB::table('clinic_memberships')->where('id', $membership)->update(['active_until' => now(), 'updated_at' => now()]);

        return redirect()->route('network.index', ['locale' => $locale])->with('status', __('network.status_messages.membership_revoked'));
    }

    private function isDemoIdentity(string $email): bool
    {
        return in_array($email, array_column(PanelDemoRegistry::identities(), 'email'), true);
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->role === UserRole::Owner && $request->user()->is_active, 403);
    }
}
