<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cases\Enums\HomeServiceStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\HomeServiceRequest;
use App\Models\HomeServiceStatusEvent;
use App\Models\PatientCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class HomeServiceController extends Controller
{
    public function show(Request $request, HomeServiceRequest $homeService): JsonResponse
    {
        abort_unless($this->canView($request, $homeService), 404);

        return response()->json(['data' => $this->resource($homeService)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function indexForCase(Request $request, PatientCase $case): JsonResponse
    {
        abort_unless($request->user()->can('view', $case), 404);

        $homeService = HomeServiceRequest::query()
            ->where('case_id', $case->id)
            ->orderByDesc('created_at')
            ->firstOrFail();

        return response()->json(['data' => $this->resource($homeService)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function transition(Request $request, HomeServiceRequest $homeService): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::Coordinator || $request->user()->role === UserRole::ClinicRepresentative, 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(HomeServiceStatus::class)],
            'version' => ['required', 'integer', 'min:1'],
            'provider_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $target = HomeServiceStatus::from($data['status']);

        return DB::transaction(function () use ($request, $homeService, $data, $target): JsonResponse {
            $locked = HomeServiceRequest::query()->lockForUpdate()->findOrFail($homeService->id);
            abort_unless($locked->version === (int) $data['version'], 409);
            abort_unless($locked->status->canTransitionTo($target), 422);

            $updates = ['status' => $target, 'version' => $locked->version + 1];
            $providerId = $data['provider_user_id'] ?? null;
            if ($providerId !== null && in_array($target, [HomeServiceStatus::ProviderRequested, HomeServiceStatus::ProviderAccepted], true)) {
                $updates['provider_user_id'] = $providerId;
                if ($target === HomeServiceStatus::ProviderAccepted) {
                    $updates['provider_acceptance_status'] = 'accepted';
                }
            }
            if ($target === HomeServiceStatus::PatientConfirmed) {
                $updates['patient_confirmed_at'] = now();
            }
            if ($target === HomeServiceStatus::Scheduled && ! empty($data['scheduled_for'])) {
                $updates['scheduled_for'] = $data['scheduled_for'];
            }
            if ($target === HomeServiceStatus::Cancelled) {
                $updates['cancelled_at'] = now();
                $updates['cancel_reason'] = $data['reason'] ?? null;
            }
            if ($target === HomeServiceStatus::Completed) {
                $updates['completed_at'] = now();
            }

            $from = $locked->status;
            $locked->update($updates);

            HomeServiceStatusEvent::query()->create([
                'id' => (string) Str::ulid(),
                'home_service_request_id' => $locked->id,
                'actor_user_id' => $request->user()->id,
                'from_status' => $from->value,
                'to_status' => $target->value,
                'reason' => $data['reason'] ?? null,
                'created_at' => now(),
            ]);

            return response()->json(['data' => $this->resource($locked->refresh())]);
        });
    }

    public function confirm(Request $request, HomeServiceRequest $homeService): JsonResponse
    {
        abort_unless((int) $homeService->patient_user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($request, $homeService, $data): JsonResponse {
            $locked = HomeServiceRequest::query()->lockForUpdate()->findOrFail($homeService->id);
            abort_unless($locked->version === (int) $data['version'], 409);
            abort_unless($locked->status === HomeServiceStatus::ProviderAccepted, 422);

            $from = $locked->status;
            $locked->update(['status' => HomeServiceStatus::PatientConfirmed, 'patient_confirmed_at' => now(), 'version' => $locked->version + 1]);
            HomeServiceStatusEvent::query()->create([
                'id' => (string) Str::ulid(),
                'home_service_request_id' => $locked->id,
                'actor_user_id' => $request->user()->id,
                'from_status' => $from->value,
                'to_status' => HomeServiceStatus::PatientConfirmed->value,
                'reason' => 'patient_confirmed',
                'created_at' => now(),
            ]);

            return response()->json(['data' => $this->resource($locked->refresh())]);
        });
    }

    public function resource(HomeServiceRequest $homeService): array
    {
        return [
            'id' => $homeService->id,
            'case_id' => $homeService->case_id,
            'status' => $homeService->status->value,
            'tehran_area' => $homeService->tehran_area,
            'provider_user_id' => $homeService->provider_user_id,
            'patient_confirmed_at' => $homeService->patient_confirmed_at?->toIso8601String(),
            'scheduled_for' => $homeService->scheduled_for?->toIso8601String(),
            'completed_at' => $homeService->completed_at?->toIso8601String(),
            'cancelled_at' => $homeService->cancelled_at?->toIso8601String(),
            'version' => $homeService->version,
        ];
    }

    private function canView(Request $request, HomeServiceRequest $homeService): bool
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Patient => (int) $homeService->patient_user_id === (int) $user->id,
            UserRole::Coordinator, UserRole::Clinician => $user->can('view', $homeService->case),
            UserRole::ClinicRepresentative => $homeService->provider_user_id === $user->id || $user->can('view', $homeService->case),
            default => false,
        };
    }
}
