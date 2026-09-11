<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\UserRole;
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

        $clinics = DB::table('clinics')->orderBy('name')->get();
        $staff = DB::table('users')
            ->leftJoin('practitioners', 'practitioners.user_id', '=', 'users.id')
            ->whereIn('users.role', [UserRole::Coordinator->value, UserRole::Clinician->value, UserRole::ClinicRepresentative->value])
            ->orderBy('users.role')->orderBy('users.name')
            ->get([
                'users.id', 'users.name', 'users.role', 'users.locale', 'users.is_active',
                'practitioners.id as practitioner_id', 'practitioners.credential_status', 'practitioners.verified_at', 'practitioners.expires_at',
            ]);
        $memberships = DB::table('clinic_memberships')
            ->join('clinics', 'clinics.id', '=', 'clinic_memberships.clinic_id')
            ->join('users', 'users.id', '=', 'clinic_memberships.user_id')
            ->orderBy('clinics.name')->orderBy('users.name')
            ->get([
                'clinic_memberships.id', 'clinic_memberships.membership_role', 'clinic_memberships.active_from', 'clinic_memberships.active_until',
                'clinics.name as clinic_name', 'clinics.id as clinic_id', 'users.name as user_name', 'users.id as user_id', 'users.role as user_role',
            ]);

        return view('panel.network.index', compact('clinics', 'staff', 'memberships', 'locale'));
    }

    public function storeClinic(Request $request, string $locale): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'city' => ['required', 'string', 'max:80'],
            'area_code' => ['nullable', 'string', 'max:80'],
        ]);

        DB::table('clinics')->insert([
            'id' => (string) Str::ulid(),
            'name' => $data['name'],
            'city' => $data['city'],
            'area_code' => $data['area_code'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('network.index', ['locale' => $locale])->with('status', 'clinic_saved');
    }

    public function updateClinic(Request $request, string $locale, string $clinic): RedirectResponse
    {
        $this->authorizeOwner($request);
        abort_unless(DB::table('clinics')->where('id', $clinic)->exists(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
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

        return redirect()->route('network.index', ['locale' => $locale])->with('status', 'clinic_saved');
    }

    public function savePractitioner(Request $request, string $locale, int $user): RedirectResponse
    {
        $this->authorizeOwner($request);
        $staff = DB::table('users')->where('id', $user)->first(['id', 'role', 'is_active']);
        abort_unless($staff && $staff->role === UserRole::Clinician->value, 404);

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

        return redirect()->route('network.index', ['locale' => $locale])->with('status', 'credential_saved');
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

        $staff = DB::table('users')->where('id', $data['user_id'])->first(['id', 'role', 'is_active']);
        abort_unless($staff && $staff->is_active, 422);
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

        return redirect()->route('network.index', ['locale' => $locale])->with('status', 'membership_saved');
    }

    public function revokeMembership(Request $request, string $locale, string $membership): RedirectResponse
    {
        $this->authorizeOwner($request);
        abort_unless(DB::table('clinic_memberships')->where('id', $membership)->exists(), 404);
        DB::table('clinic_memberships')->where('id', $membership)->update(['active_until' => now(), 'updated_at' => now()]);

        return redirect()->route('network.index', ['locale' => $locale])->with('status', 'membership_revoked');
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->role === UserRole::Owner && $request->user()->is_active, 403);
    }
}
