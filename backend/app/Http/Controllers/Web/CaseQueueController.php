<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PatientCase;
use App\Support\PanelDemoRegistry;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CaseQueueController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        $user = $request->user();
        abort_unless(
            $user?->is_active
            && in_array($user->role, [UserRole::Coordinator, UserRole::Clinician], true),
            403,
        );

        $purpose = $user->role === UserRole::Coordinator ? 'coordination' : 'clinical_review';

        $query = PatientCase::query()
            ->select([
                'patient_cases.id',
                'patient_cases.public_reference',
                'patient_cases.service_type',
                'patient_cases.status',
                'patient_cases.priority',
                'patient_cases.submitted_at',
                'patient_cases.updated_at',
            ])
            ->join('case_assignments', 'case_assignments.case_id', '=', 'patient_cases.id')
            ->where('case_assignments.assignee_user_id', $user->id)
            ->where('case_assignments.purpose', $purpose)
            ->whereNull('case_assignments.released_at')
            ->distinct();

        if ((bool) $request->session()->get('panel_demo', false)) {
            $query->where('patient_cases.public_reference', 'like', PanelDemoRegistry::CASE_REFERENCE_PREFIX.'%');
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:40'],
            'service' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', Rule::in(['normal', 'urgent'])],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where('patient_cases.public_reference', 'like', '%'.$search.'%');
        }

        if (! empty($filters['status'])) {
            $query->where('patient_cases.status', $filters['status']);
        }

        if (! empty($filters['service'])) {
            $query->where('patient_cases.service_type', $filters['service']);
        }

        if ($user->role === UserRole::Coordinator && ! empty($filters['priority'])) {
            $query->where('patient_cases.priority', $filters['priority']);
        }

        $cases = $query
            ->orderByRaw("case when patient_cases.priority = 'urgent' then 0 else 1 end")
            ->orderByDesc('patient_cases.updated_at')
            ->paginate(40)
            ->withQueryString();

        $statusOptions = DB::table('patient_cases')
            ->join('case_assignments', 'case_assignments.case_id', '=', 'patient_cases.id')
            ->where('case_assignments.assignee_user_id', $user->id)
            ->where('case_assignments.purpose', $purpose)
            ->whereNull('case_assignments.released_at')
            ->distinct()
            ->orderBy('patient_cases.status')
            ->pluck('patient_cases.status');

        $serviceOptions = DB::table('patient_cases')
            ->join('case_assignments', 'case_assignments.case_id', '=', 'patient_cases.id')
            ->where('case_assignments.assignee_user_id', $user->id)
            ->where('case_assignments.purpose', $purpose)
            ->whereNull('case_assignments.released_at')
            ->distinct()
            ->orderBy('patient_cases.service_type')
            ->pluck('patient_cases.service_type');

        return view('panel.cases.index', [
            ...WorkspaceView::data($request, 'cases'),
            'cases' => $cases,
            'filters' => [
                'q' => $search,
                'status' => (string) ($filters['status'] ?? ''),
                'service' => (string) ($filters['service'] ?? ''),
                'priority' => (string) ($filters['priority'] ?? ''),
            ],
            'statusOptions' => $statusOptions,
            'serviceOptions' => $serviceOptions,
            'role' => $user->role,
            'isCoordinator' => $user->role === UserRole::Coordinator,
        ])->with('locale', $locale);
    }
}
