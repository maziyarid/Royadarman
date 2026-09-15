<?php

namespace App\Http\Controllers\Web;

use App\Domain\Cases\Enums\HomeServiceStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\HomeServiceRequest;
use App\Models\HomeServiceStatusEvent;
use App\Support\PanelDemoRegistry;
use App\Support\WorkspaceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class HomeServiceWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [
            UserRole::Patient,
            UserRole::Coordinator,
            UserRole::ClinicRepresentative,
        ], true), 403);

        $query = HomeServiceRequest::query()
            ->with(['case:id,public_reference,service_type,status', 'patient:id,name'])
            ->latest();

        if ($user->role === UserRole::Patient) {
            $query->where('patient_user_id', $user->id);
        } elseif ($user->role === UserRole::Coordinator) {
            $query->whereHas('case.assignments', function ($assignment) use ($user): void {
                $assignment->where('assignee_user_id', $user->id)
                    ->where('purpose', 'coordination')
                    ->whereNull('released_at');
            });
        } elseif ($user->role === UserRole::ClinicRepresentative) {
            $query->where('provider_user_id', $user->id);
        }

        if ((bool) $request->session()->get('panel_demo', false)) {
            $query->whereHas(
                'case',
                fn ($case) => $case->where('public_reference', 'like', PanelDemoRegistry::CASE_REFERENCE_PREFIX.'%'),
            );
        }

        $status = (string) $request->query('status', '');
        if ($status !== '' && in_array($status, array_column(HomeServiceStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        return view('panel.home-service.index', [
            ...WorkspaceView::data($request, 'home-service'),
            'requests' => $query->paginate(20)->withQueryString(),
            'filterStatus' => $status,
        ]);
    }

    public function show(Request $request, string $locale, HomeServiceRequest $homeService): View
    {
        abort_unless($this->canView($request, $homeService), 404);

        $homeService->load(['case:id,public_reference,service_type,status,tehran_area', 'patient:id,name', 'statusEvents']);

        return view('panel.home-service.show', [
            ...WorkspaceView::data($request, 'home-service'),
            'homeService' => $homeService,
            'allowedTargets' => $request->user()->role === UserRole::Coordinator
                ? $homeService->status->allowedTargets()
                : [],
        ]);
    }

    public function transition(Request $request, string $locale, HomeServiceRequest $homeService): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, [UserRole::Coordinator, UserRole::ClinicRepresentative], true), 404);

        $data = $request->validate([
            'status' => ['required', Rule::enum(HomeServiceStatus::class)],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $target = HomeServiceStatus::from($data['status']);

        DB::transaction(function () use ($request, $homeService, $data, $target): void {
            $locked = HomeServiceRequest::query()->lockForUpdate()->findOrFail($homeService->id);
            abort_unless($locked->version === (int) $data['version'], 409);
            abort_unless($locked->status->canTransitionTo($target), 422);

            $from = $locked->status;
            $updates = ['status' => $target, 'version' => $locked->version + 1];
            if ($target === HomeServiceStatus::Cancelled) {
                $updates['cancelled_at'] = now();
                $updates['cancel_reason'] = $data['reason'] ?? null;
            }
            if ($target === HomeServiceStatus::Completed) {
                $updates['completed_at'] = now();
            }
            $locked->update($updates);

            HomeServiceStatusEvent::query()->create([
                'home_service_request_id' => $locked->id,
                'actor_user_id' => $request->user()->id,
                'from_status' => $from->value,
                'to_status' => $target->value,
                'reason' => $data['reason'] ?? null,
                'created_at' => now(),
            ]);
        });

        return back()->with('status', __('panel.saved'));
    }

    public function confirm(Request $request, string $locale, HomeServiceRequest $homeService): RedirectResponse
    {
        abort_unless((int) $homeService->patient_user_id === (int) $request->user()->id, 404);

        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        DB::transaction(function () use ($request, $homeService, $data): void {
            $locked = HomeServiceRequest::query()->lockForUpdate()->findOrFail($homeService->id);
            abort_unless($locked->version === (int) $data['version'], 409);
            abort_unless($locked->status === HomeServiceStatus::ProviderAccepted, 422);

            $from = $locked->status;
            $locked->update([
                'status' => HomeServiceStatus::PatientConfirmed,
                'patient_confirmed_at' => now(),
                'version' => $locked->version + 1,
            ]);
            HomeServiceStatusEvent::query()->create([
                'home_service_request_id' => $locked->id,
                'actor_user_id' => $request->user()->id,
                'from_status' => $from->value,
                'to_status' => HomeServiceStatus::PatientConfirmed->value,
                'reason' => 'patient_confirmed',
                'created_at' => now(),
            ]);
        });

        return back()->with('status', __('panel.saved'));
    }

    private function canView(Request $request, HomeServiceRequest $homeService): bool
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Patient => (int) $homeService->patient_user_id === (int) $user->id,
            UserRole::Coordinator, UserRole::Clinician => $user->can('view', $homeService->case),
            UserRole::ClinicRepresentative => (int) $homeService->provider_user_id === (int) $user->id
                || $user->can('view', $homeService->case),
            default => false,
        };
    }
}
