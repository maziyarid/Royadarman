<?php

namespace App\Http\Controllers\Api\V1\Clinics;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ServiceController extends Controller
{
    public function index(Request $request, Clinic $clinic): JsonResponse
    {
        $services = $clinic->services()
            ->with(['categories', 'branchServices'])
            ->orderBy('name_fa')
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => [
            'services' => $services->through(fn ($s) => $this->resource($s)),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
            ],
        ]]);
    }

    public function store(Request $request, Clinic $clinic): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_fa' => ['required', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:services,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_fa' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'category_fa' => ['nullable', 'string', 'max:100'],
            'category_ar' => ['nullable', 'string', 'max:100'],
            'duration_minutes' => ['integer', 'min:1'],
            'default_price' => ['integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'is_active' => ['boolean'],
            'is_standard' => ['boolean'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $clinic, $data): JsonResponse {
            $service = Service::query()->create([
                'id' => (string) Str::ulid(),
                'clinic_id' => $clinic->id,
                'public_reference' => 'SVC-' . strtoupper(Str::random(8)),
                'code' => $data['code'],
                'name' => $data['name'],
                'name_fa' => $data['name_fa'],
                'name_ar' => $data['name_ar'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'],
                'description_fa' => $data['description_fa'],
                'description_ar' => $data['description_ar'],
                'category' => $data['category'],
                'category_fa' => $data['category_fa'],
                'category_ar' => $data['category_ar'],
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'default_price' => $data['default_price'] ?? 0,
                'currency' => $data['currency'] ?? 'IRR',
                'is_active' => $data['is_active'] ?? true,
                'is_standard' => $data['is_standard'] ?? false,
                'preferences' => $data['preferences'] ?? [],
                'metadata' => [],
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($service)], 201);
        });
    }

    public function show(Request $request, Service $service): JsonResponse
    {
        $service->load([
            'clinic',
            'categories',
            'branchServices.clinicBranch',
            'appointments',
        ]);

        return response()->json(['data' => $this->resource($service)]);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'name_fa' => ['sometimes', 'string', 'max:100'],
            'name_ar' => ['nullable', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:20', 'unique:services,code,' . $service->id],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_fa' => ['nullable', 'string', 'max:2000'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'category_fa' => ['nullable', 'string', 'max:100'],
            'category_ar' => ['nullable', 'string', 'max:100'],
            'duration_minutes' => ['integer', 'min:1'],
            'default_price' => ['integer', 'min:0'],
            'currency' => ['string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
            'is_standard' => ['sometimes', 'boolean'],
            'preferences' => ['nullable', 'array'],
        ]);

        return DB::transaction(function () use ($request, $service, $data): JsonResponse {
            $service->update([
                'name' => $data['name'] ?? $service->name,
                'name_fa' => $data['name_fa'] ?? $service->name_fa,
                'name_ar' => $data['name_ar'] ?? $service->name_ar,
                'code' => $data['code'] ?? $service->code,
                'description' => $data['description'] ?? $service->description,
                'description_fa' => $data['description_fa'] ?? $service->description_fa,
                'description_ar' => $data['description_ar'] ?? $service->description_ar,
                'category' => $data['category'] ?? $service->category,
                'category_fa' => $data['category_fa'] ?? $service->category_fa,
                'category_ar' => $data['category_ar'] ?? $service->category_ar,
                'duration_minutes' => $data['duration_minutes'] ?? $service->duration_minutes,
                'default_price' => $data['default_price'] ?? $service->default_price,
                'currency' => $data['currency'] ?? $service->currency,
                'is_active' => $data['is_active'] ?? $service->is_active,
                'is_standard' => $data['is_standard'] ?? $service->is_standard,
                'preferences' => $data['preferences'] ?? $service->preferences,
                'updated_by_user_id' => $request->user()->id,
            ]);

            return response()->json(['data' => $this->resource($service->refresh())]);
        });
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        abort_unless($request->user()->can('delete', $service), 404);

        // Check if service is used in active appointments
        $hasActiveAppointments = $service->appointments()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->exists();

        if ($hasActiveAppointments) {
            abort(422, 'Cannot delete service used in active appointments');
        }

        $service->delete();

        return response()->json(['data' => ['message' => 'Service deleted successfully']]);
    }

    private function resource(Service $service): array
    {
        return [
            'id' => $service->id,
            'clinic_id' => $service->clinic_id,
            'clinic_name' => $service->clinic?->name,
            'public_reference' => $service->public_reference,
            'code' => $service->code,
            'name' => $service->name,
            'name_fa' => $service->name_fa,
            'name_ar' => $service->name_ar,
            'slug' => $service->slug,
            'description' => $service->description,
            'description_fa' => $service->description_fa,
            'description_ar' => $service->description_ar,
            'category' => $service->category,
            'category_fa' => $service->category_fa,
            'category_ar' => $service->category_ar,
            'duration_minutes' => $service->duration_minutes,
            'default_price' => $service->default_price,
            'currency' => $service->currency,
            'is_active' => $service->is_active,
            'is_standard' => $service->is_standard,
            'preferences' => $service->preferences,
            'created_at' => $service->created_at->toIso8601String(),
            'updated_at' => $service->updated_at->toIso8601String(),
        ];
    }
}
