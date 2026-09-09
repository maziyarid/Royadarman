<?php

namespace App\Http\Controllers\Api\V1\Clinics;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Dentist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DentistController extends Controller
{
    public function index(Request $request, Clinic $clinic): JsonResponse
    {
        $dentists = $clinic->dentists()
            ->with(['credentials', 'specialties', 'branchAssignments.clinicBranch'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => [
            'dentists' => $dentists->through(fn ($d) => $this->resource($d)),
            'pagination' => [
                'current_page' => $dentists->currentPage(),
                'per_page' => $dentists->perPage(),
                'total' => $dentists->total(),
                'last_page' => $dentists->lastPage(),
            ],
        ]]);
    }

    public function store(Request $request, Clinic $clinic): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'first_name_fa' => ['required', 'string', 'max:80'],
            'last_name_fa' => ['required', 'string', 'max:80'],
            'first_name_ar' => ['nullable', 'string', 'max:80'],
            'last_name_ar' => ['nullable', 'string', 'max:80'],
            'license_number' => ['required', 'string', 'max:50'],
            'license_issuer' => ['required', 'string', 'max:100'],
            'license_issued_at' => ['required', 'date'],
            'license_expires_at' => ['nullable', 'date'],
            'specialty' => ['required', 'string', 'max:100'],
            'specialty_fa' => ['required', 'string', 'max:100'],
            'specialty_ar' => ['nullable', 'string', 'max:100'],
            'experience_years' => ['integer', 'min:0'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'bio_fa' => ['nullable', 'string', 'max:2000'],
            'bio_ar' => ['nullable', 'string', 'max:2000'],
            'gender' => ['required', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $clinic, $data): JsonResponse {
            $dentist = Dentist::query()->create([
                'id' => (string) Str::ulid(),
                'clinic_id' => $clinic->id,
                'public_reference' => 'DENTIST-' . strtoupper(Str::random(8)),
                'user_id' => null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'first_name_fa' => $data['first_name_fa'],
                'last_name_fa' => $data['last_name_fa'],
                'first_name_ar' => $data['first_name_ar'],
                'last_name_ar' => $data['last_name_ar'],
                'full_name' => $data['first_name'] . ' ' . $data['last_name'],
                'full_name_fa' => $data['first_name_fa'] . ' ' . $data['last_name_fa'],
                'full_name_ar' => ($data['first_name_ar'] ?? '') . ' ' . ($data['last_name_ar'] ?? ''),
                'license_number' => $data['license_number'],
                'license_number_hash' => hash_hmac('sha256', $data['license_number'], (string) config('app.key')),
                'license_issuer' => $data['license_issuer'],
                'license_issued_at' => $data['license_issued_at'],
                'license_expires_at' => $data['license_expires_at'],
                'license_verified_at' => null,
                'specialty' => $data['specialty'],
                'specialty_fa' => $data['specialty_fa'],
                'specialty_ar' => $data['specialty_ar'],
                'experience_years' => $data['experience_years'] ?? 0,
                'bio' => $data['bio'],
                'bio_fa' => $data['bio_fa'],
                'bio_ar' => $data['bio_ar'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'national_id' => $data['national_id'] ?? null,
                'national_id_hash' => $data['national_id'] ? hash_hmac('sha256', $data['national_id'], (string) config('app.key')) : null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'phone_hash' => $data['phone'] ? hash_hmac('sha256', $data['phone'], (string) config('royadarman.phone_hash_key')) : null,
                'is_active' => $data['is_active'] ?? true,
                'is_verified' => false,
                'preferences' => $data['preferences'] ?? [],
                'metadata' => [],
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($dentist)], 201);
        });
    }

    public function show(Request $request, Dentist $dentist): JsonResponse
    {
        $dentist->load([
            'clinic',
            'credentials',
            'branchAssignments.clinicBranch',
            'appointments',
            'reviews',
        ]);

        return response()->json(['data' => $this->resource($dentist)]);
    }

    public function update(Request $request, Dentist $dentist): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:80'],
            'last_name' => ['sometimes', 'string', 'max:80'],
            'first_name_fa' => ['sometimes', 'string', 'max:80'],
            'last_name_fa' => ['sometimes', 'string', 'max:80'],
            'first_name_ar' => ['nullable', 'string', 'max:80'],
            'last_name_ar' => ['nullable', 'string', 'max:80'],
            'license_number' => ['sometimes', 'string', 'max:50'],
            'license_issuer' => ['sometimes', 'string', 'max:100'],
            'license_issued_at' => ['sometimes', 'date'],
            'license_expires_at' => ['nullable', 'date'],
            'specialty' => ['sometimes', 'string', 'max:100'],
            'specialty_fa' => ['sometimes', 'string', 'max:100'],
            'specialty_ar' => ['nullable', 'string', 'max:100'],
            'experience_years' => ['integer', 'min:0'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'bio_fa' => ['nullable', 'string', 'max:2000'],
            'bio_ar' => ['nullable', 'string', 'max:2000'],
            'gender' => ['sometimes', 'in:male,female'],
            'date_of_birth' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'is_verified' => ['sometimes', 'boolean'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $dentist, $data): JsonResponse {
            $dentist->update([
                'first_name' => $data['first_name'] ?? $dentist->first_name,
                'last_name' => $data['last_name'] ?? $dentist->last_name,
                'first_name_fa' => $data['first_name_fa'] ?? $dentist->first_name_fa,
                'last_name_fa' => $data['last_name_fa'] ?? $dentist->last_name_fa,
                'first_name_ar' => $data['first_name_ar'] ?? $dentist->first_name_ar,
                'last_name_ar' => $data['last_name_ar'] ?? $dentist->last_name_ar,
                'full_name' => ($data['first_name'] ?? $dentist->first_name) . ' ' . ($data['last_name'] ?? $dentist->last_name),
                'full_name_fa' => ($data['first_name_fa'] ?? $dentist->first_name_fa) . ' ' . ($data['last_name_fa'] ?? $dentist->last_name_fa),
                'full_name_ar' => ($data['first_name_ar'] ?? $dentist->first_name_ar ?? '') . ' ' . ($data['last_name_ar'] ?? $dentist->last_name_ar ?? ''),
                'license_number' => $data['license_number'] ?? $dentist->license_number,
                'license_number_hash' => $data['license_number'] ? hash_hmac('sha256', $data['license_number'], (string) config('app.key')) : $dentist->license_number_hash,
                'license_issuer' => $data['license_issuer'] ?? $dentist->license_issuer,
                'license_issued_at' => $data['license_issued_at'] ?? $dentist->license_issued_at,
                'license_expires_at' => $data['license_expires_at'] ?? $dentist->license_expires_at,
                'specialty' => $data['specialty'] ?? $dentist->specialty,
                'specialty_fa' => $data['specialty_fa'] ?? $dentist->specialty_fa,
                'specialty_ar' => $data['specialty_ar'] ?? $dentist->specialty_ar,
                'experience_years' => $data['experience_years'] ?? $dentist->experience_years,
                'bio' => $data['bio'] ?? $dentist->bio,
                'bio_fa' => $data['bio_fa'] ?? $dentist->bio_fa,
                'bio_ar' => $data['bio_ar'] ?? $dentist->bio_ar,
                'gender' => $data['gender'] ?? $dentist->gender,
                'date_of_birth' => $data['date_of_birth'] ?? $dentist->date_of_birth,
                'national_id' => $data['national_id'] ?? $dentist->national_id,
                'national_id_hash' => $data['national_id'] ? hash_hmac('sha256', $data['national_id'], (string) config('app.key')) : $dentist->national_id_hash,
                'email' => $data['email'] ?? $dentist->email,
                'phone' => $data['phone'] ?? $dentist->phone,
                'phone_hash' => $data['phone'] ? hash_hmac('sha256', $data['phone'], (string) config('royadarman.phone_hash_key')) : $dentist->phone_hash,
                'is_active' => $data['is_active'] ?? $dentist->is_active,
                'is_verified' => $data['is_verified'] ?? $dentist->is_verified,
                'preferences' => $data['preferences'] ?? $dentist->preferences,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($dentist->refresh())]);
        });
    }

    public function destroy(Request $request, Dentist $dentist): JsonResponse
    {
        abort_unless($request->user()->can('delete', $dentist), 404);

        // Check if dentist has active appointments
        $hasActiveAppointments = $dentist->appointments()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($hasActiveAppointments) {
            abort(422, 'Cannot delete dentist with active appointments');
        }

        $dentist->delete();

        return response()->json(['data' => ['message' => 'Dentist deleted successfully']]);
    }

    private function resource(Dentist $dentist): array
    {
        return [
            'id' => $dentist->id,
            'clinic_id' => $dentist->clinic_id,
            'clinic_name' => $dentist->clinic?->name,
            'public_reference' => $dentist->public_reference,
            'user_id' => $dentist->user_id,
            'first_name' => $dentist->first_name,
            'last_name' => $dentist->last_name,
            'full_name' => $dentist->full_name,
            'first_name_fa' => $dentist->first_name_fa,
            'last_name_fa' => $dentist->last_name_fa,
            'full_name_fa' => $dentist->full_name_fa,
            'first_name_ar' => $dentist->first_name_ar,
            'last_name_ar' => $dentist->last_name_ar,
            'full_name_ar' => $dentist->full_name_ar,
            'license_number' => $dentist->license_number,
            'license_issuer' => $dentist->license_issuer,
            'license_issued_at' => $dentist->license_issued_at?->toDateString(),
            'license_expires_at' => $dentist->license_expires_at?->toDateString(),
            'license_verified_at' => $dentist->license_verified_at?->toIso8601String(),
            'specialty' => $dentist->specialty,
            'specialty_fa' => $dentist->specialty_fa,
            'specialty_ar' => $dentist->specialty_ar,
            'experience_years' => $dentist->experience_years,
            'bio' => $dentist->bio,
            'bio_fa' => $dentist->bio_fa,
            'bio_ar' => $dentist->bio_ar,
            'gender' => $dentist->gender,
            'date_of_birth' => $dentist->date_of_birth?->toDateString(),
            'national_id' => $dentist->national_id,
            'email' => $dentist->email,
            'phone' => $dentist->phone,
            'is_active' => $dentist->is_active,
            'is_verified' => $dentist->is_verified,
            'preferences' => $dentist->preferences,
            'created_at' => $dentist->created_at->toIso8601String(),
            'updated_at' => $dentist->updated_at->toIso8601String(),
        ];
    }
}
