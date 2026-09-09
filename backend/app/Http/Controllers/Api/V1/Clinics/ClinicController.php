<?php

namespace App\Http\Controllers\Api\V1\Clinics;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClinicController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['integer', 'min:1', 'max:100'],
            'page' => ['integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'is_verified' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $query = Clinic::query()
            ->with(['branches', 'payoutAccounts', 'credentialing'])
            ->withCount(['branches', 'dentists', 'users']);

        if (isset($data['search'])) {
            $query->where(function ($q) use ($data) {
                $q->where('name', 'ilike', '%' . $data['search'] . '%')
                    ->orWhere('description', 'ilike', '%' . $data['search'] . '%');
            });
        }

        if (isset($data['is_verified'])) {
            $query->where('is_verified', $data['is_verified']);
        }

        if (isset($data['is_active'])) {
            $query->where('is_active', $data['is_active']);
        }

        $clinics = $query->orderBy('name')->paginate($data['per_page'] ?? 20);

        return response()->json(['data' => [
            'clinics' => $clinics->through(fn ($c) => $this->resource($c)),
            'pagination' => [
                'current_page' => $clinics->currentPage(),
                'per_page' => $clinics->perPage(),
                'total' => $clinics->total(),
                'last_page' => $clinics->lastPage(),
            ],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_fa' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_fa' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'established_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'is_verified' => ['boolean'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            $clinic = Clinic::query()->create([
                'id' => (string) Str::ulid(),
                'public_reference' => 'CLINIC-' . strtoupper(Str::random(8)),
                'name' => $data['name'],
                'name_fa' => $data['name_fa'],
                'name_ar' => $data['name_ar'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'],
                'description_fa' => $data['description_fa'],
                'description_ar' => $data['description_ar'],
                'website' => $data['website'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'phone_hash' => $data['phone'] ? hash_hmac('sha256', $data['phone'], (string) config('royadarman.phone_hash_key')) : null,
                'national_id' => $data['national_id'] ?? null,
                'national_id_hash' => $data['national_id'] ? hash_hmac('sha256', $data['national_id'], (string) config('app.key')) : null,
                'registration_number' => $data['registration_number'] ?? null,
                'established_at' => $data['established_at'],
                'is_active' => $data['is_active'] ?? true,
                'is_verified' => $data['is_verified'] ?? false,
                'verification_notes' => $data['verification_notes'],
                'verification_token' => (string) Str::ulid(),
                'verified_at' => null,
                'preferences' => $data['preferences'] ?? [],
                'metadata' => [],
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($clinic)], 201);
        });
    }

    public function show(Request $request, Clinic $clinic): JsonResponse
    {
        $clinic->load([
            'branches.operatingHours',
            'branches.holidays',
            'branches.services.service',
            'branches.bookingModes',
            'dentists.credentials',
            'users',
            'payoutAccounts',
            'credentialing',
            'documentVerifications',
        ]);

        return response()->json(['data' => $this->resource($clinic)]);
    }

    public function update(Request $request, Clinic $clinic): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'name_fa' => ['sometimes', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_fa' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'established_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'is_verified' => ['sometimes', 'boolean'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $clinic, $data): JsonResponse {
            $clinic->update([
                'name' => $data['name'] ?? $clinic->name,
                'name_fa' => $data['name_fa'] ?? $clinic->name_fa,
                'name_ar' => $data['name_ar'] ?? $clinic->name_ar,
                'description' => $data['description'] ?? $clinic->description,
                'description_fa' => $data['description_fa'] ?? $clinic->description_fa,
                'description_ar' => $data['description_ar'] ?? $clinic->description_ar,
                'website' => $data['website'] ?? $clinic->website,
                'email' => $data['email'] ?? $clinic->email,
                'phone' => $data['phone'] ?? $clinic->phone,
                'phone_hash' => $data['phone'] ? hash_hmac('sha256', $data['phone'], (string) config('royadarman.phone_hash_key')) : $clinic->phone_hash,
                'national_id' => $data['national_id'] ?? $clinic->national_id,
                'national_id_hash' => $data['national_id'] ? hash_hmac('sha256', $data['national_id'], (string) config('app.key')) : $clinic->national_id_hash,
                'registration_number' => $data['registration_number'] ?? $clinic->registration_number,
                'established_at' => $data['established_at'] ?? $clinic->established_at,
                'is_active' => $data['is_active'] ?? $clinic->is_active,
                'is_verified' => $data['is_verified'] ?? $clinic->is_verified,
                'verification_notes' => $data['verification_notes'] ?? $clinic->verification_notes,
                'preferences' => $data['preferences'] ?? $clinic->preferences,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($clinic->refresh())]);
        });
    }

    public function destroy(Request $request, Clinic $clinic): JsonResponse
    {
        abort_unless($request->user()->can('delete', $clinic), 404);

        // Check if clinic has active appointments
        $hasActiveAppointments = $clinic->appointments()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($hasActiveAppointments) {
            abort(422, 'Cannot delete clinic with active appointments');
        }

        $clinic->delete();

        return response()->json(['data' => ['message' => 'Clinic deleted successfully']]);
    }

    private function resource(Clinic $clinic): array
    {
        return [
            'id' => $clinic->id,
            'public_reference' => $clinic->public_reference,
            'name' => $clinic->name,
            'name_fa' => $clinic->name_fa,
            'name_ar' => $clinic->name_ar,
            'slug' => $clinic->slug,
            'description' => $clinic->description,
            'description_fa' => $clinic->description_fa,
            'description_ar' => $clinic->description_ar,
            'website' => $clinic->website,
            'email' => $clinic->email,
            'phone' => $clinic->phone,
            'national_id' => $clinic->national_id,
            'registration_number' => $clinic->registration_number,
            'established_at' => $clinic->established_at?->toDateString(),
            'is_active' => $clinic->is_active,
            'is_verified' => $clinic->is_verified,
            'verification_notes' => $clinic->verification_notes,
            'verified_at' => $clinic->verified_at?->toIso8601String(),
            'preferences' => $clinic->preferences,
            'branch_count' => $clinic->branches_count,
            'dentist_count' => $clinic->dentists_count,
            'user_count' => $clinic->users_count,
            'created_at' => $clinic->created_at->toIso8601String(),
            'updated_at' => $clinic->updated_at->toIso8601String(),
        ];
    }
}
