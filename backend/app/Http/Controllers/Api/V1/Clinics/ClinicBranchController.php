<?php

namespace App\Http\Controllers\Api\V1\Clinics;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\ClinicBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ClinicBranchController extends Controller
{
    public function index(Request $request, Clinic $clinic): JsonResponse
    {
        $branches = $clinic->branches()
            ->with(['operatingHours', 'holidays', 'services.service', 'bookingModes'])
            ->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => [
            'branches' => $branches->through(fn ($b) => $this->resource($b)),
            'pagination' => [
                'current_page' => $branches->currentPage(),
                'per_page' => $branches->perPage(),
                'total' => $branches->total(),
                'last_page' => $branches->lastPage(),
            ],
        ]]);
    }

    public function store(Request $request, Clinic $clinic): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_fa' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'address_fa' => ['required', 'string', 'max:500'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],
            'tehran_area' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'is_main' => ['boolean'],
            'capacity' => ['integer', 'min:1'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $clinic, $data): JsonResponse {
            $branch = ClinicBranch::query()->create([
                'id' => (string) Str::ulid(),
                'clinic_id' => $clinic->id,
                'public_reference' => 'BRANCH-' . strtoupper(Str::random(8)),
                'name' => $data['name'],
                'name_fa' => $data['name_fa'],
                'name_ar' => $data['name_ar'],
                'slug' => Str::slug($data['name']),
                'address' => $data['address'],
                'address_fa' => $data['address_fa'],
                'address_ar' => $data['address_ar'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'tehran_area' => $data['tehran_area'],
                'phone' => $data['phone'] ?? null,
                'phone_hash' => $data['phone'] ? hash_hmac('sha256', $data['phone'], (string) config('royadarman.phone_hash_key')) : null,
                'email' => $data['email'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_main' => $data['is_main'] ?? false,
                'capacity' => $data['capacity'] ?? 1,
                'preferences' => $data['preferences'] ?? [],
                'metadata' => [],
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($branch)], 201);
        });
    }

    public function show(Request $request, ClinicBranch $clinicBranch): JsonResponse
    {
        $clinicBranch->load([
            'clinic',
            'operatingHours',
            'holidays',
            'services.service',
            'bookingModes',
            'appointmentSlots',
            'capacityWindows',
            'appointments',
        ]);

        return response()->json(['data' => $this->resource($clinicBranch)]);
    }

    public function update(Request $request, ClinicBranch $clinicBranch): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'name_fa' => ['sometimes', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'string', 'max:500'],
            'address_fa' => ['sometimes', 'string', 'max:500'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['sometimes', 'numeric', 'min:-180', 'max:180'],
            'tehran_area' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'is_main' => ['sometimes', 'boolean'],
            'capacity' => ['integer', 'min:1'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $clinicBranch, $data): JsonResponse {
            $clinicBranch->update([
                'name' => $data['name'] ?? $clinicBranch->name,
                'name_fa' => $data['name_fa'] ?? $clinicBranch->name_fa,
                'name_ar' => $data['name_ar'] ?? $clinicBranch->name_ar,
                'address' => $data['address'] ?? $clinicBranch->address,
                'address_fa' => $data['address_fa'] ?? $clinicBranch->address_fa,
                'address_ar' => $data['address_ar'] ?? $clinicBranch->address_ar,
                'latitude' => $data['latitude'] ?? $clinicBranch->latitude,
                'longitude' => $data['longitude'] ?? $clinicBranch->longitude,
                'tehran_area' => $data['tehran_area'] ?? $clinicBranch->tehran_area,
                'phone' => $data['phone'] ?? $clinicBranch->phone,
                'phone_hash' => $data['phone'] ? hash_hmac('sha256', $data['phone'], (string) config('royadarman.phone_hash_key')) : $clinicBranch->phone_hash,
                'email' => $data['email'] ?? $clinicBranch->email,
                'is_active' => $data['is_active'] ?? $clinicBranch->is_active,
                'is_main' => $data['is_main'] ?? $clinicBranch->is_main,
                'capacity' => $data['capacity'] ?? $clinicBranch->capacity,
                'preferences' => $data['preferences'] ?? $clinicBranch->preferences,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($clinicBranch->refresh())]);
        });
    }

    public function destroy(Request $request, ClinicBranch $clinicBranch): JsonResponse
    {
        abort_unless($request->user()->can('delete', $clinicBranch), 404);

        // Check if branch has active appointments
        $hasActiveAppointments = $clinicBranch->appointments()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($hasActiveAppointments) {
            abort(422, 'Cannot delete branch with active appointments');
        }

        $clinicBranch->delete();

        return response()->json(['data' => ['message' => 'Branch deleted successfully']]);
    }

    private function resource(ClinicBranch $clinicBranch): array
    {
        return [
            'id' => $clinicBranch->id,
            'clinic_id' => $clinicBranch->clinic_id,
            'clinic_name' => $clinicBranch->clinic?->name,
            'public_reference' => $clinicBranch->public_reference,
            'name' => $clinicBranch->name,
            'name_fa' => $clinicBranch->name_fa,
            'name_ar' => $clinicBranch->name_ar,
            'slug' => $clinicBranch->slug,
            'address' => $clinicBranch->address,
            'address_fa' => $clinicBranch->address_fa,
            'address_ar' => $clinicBranch->address_ar,
            'latitude' => $clinicBranch->latitude,
            'longitude' => $clinicBranch->longitude,
            'tehran_area' => $clinicBranch->tehran_area,
            'phone' => $clinicBranch->phone,
            'email' => $clinicBranch->email,
            'is_active' => $clinicBranch->is_active,
            'is_main' => $clinicBranch->is_main,
            'capacity' => $clinicBranch->capacity,
            'preferences' => $clinicBranch->preferences,
            'created_at' => $clinicBranch->created_at->toIso8601String(),
            'updated_at' => $clinicBranch->updated_at->toIso8601String(),
        ];
    }
}
