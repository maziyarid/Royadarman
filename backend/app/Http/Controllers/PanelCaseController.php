<?php

namespace App\Http\Controllers;

use App\Domain\Coordination\Services\ReferralLifecycle;
use App\Domain\Identity\Enums\UserRole;
use App\Models\PatientCase;
use App\Models\ReferralProposal;
use App\Support\PanelDemoRegistry;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PanelCaseController extends Controller
{
    public function show(Request $request, string $locale, PatientCase $case): View
    {
        abort_unless($request->user()?->can('view', $case), 404);
        $role = $request->user()->role;
        $isDemo = (bool) $request->session()->get('panel_demo', false);

        if ($isDemo && ! PanelDemoRegistry::isDemoCaseReference((string) $case->public_reference)) {
            abort(403, 'Demo sessions cannot open non-test cases.');
        }

        $data = match ($role) {
            UserRole::Patient => $this->patientData($request, $case),
            UserRole::Coordinator => $this->coordinatorData($case),
            UserRole::Clinician => $this->clinicianData($request, $case),
            UserRole::ClinicRepresentative => $this->clinicRepresentativeData($request, $case),
            default => abort(404),
        };

        return view('panel.case', [
            ...WorkspaceView::data($request, 'panel'),
            'case' => $case,
            'roleKey' => $role->value,
            'locale' => $locale,
            'isDemo' => $isDemo,
            'allowedStatuses' => $role === UserRole::Coordinator ? $case->status->allowedTargets() : [],
            ...$data,
        ]);
    }

    private function patientData(Request $request, PatientCase $case): array
    {
        $lifecycle = app(ReferralLifecycle::class);
        ReferralProposal::query()
            ->where('case_id', $case->id)
            ->where('status', 'proposed')
            ->whereNull('withdrawn_at')
            ->get()
            ->each(fn (ReferralProposal $proposal) => $lifecycle->recordViewed($proposal, $request->user()));

        return [
            'documents' => DB::table('clinical_documents')->where('case_id', $case->id)->whereNull('deleted_at')
                ->orderByDesc('created_at')->get(['id', 'original_name', 'status', 'created_at']),
            'reviews' => DB::table('review_revisions')->where('case_id', $case->id)->whereNotNull('signed_at')
                ->orderByDesc('signed_at')->get(['id', 'revision_number', 'source_language', 'image_adequacy', 'observations', 'limitations', 'options', 'recommended_next_step', 'budget_band', 'signed_at']),
            'referrals' => DB::table('referral_proposals')->join('clinics', 'clinics.id', '=', 'referral_proposals.clinic_id')
                ->where('case_id', $case->id)->orderByDesc('proposed_at')
                ->get(['referral_proposals.id', 'referral_proposals.status', 'referral_proposals.source_language', 'referral_proposals.proposed_at', 'clinics.name as clinic_name']),
            'clinics' => collect(), 'assignments' => collect(), 'draftReviews' => collect(), 'eligibleClinicians' => collect(), 'shared' => [],
        ];
    }

    private function coordinatorData(PatientCase $case): array
    {
        return [
            'documents' => collect(), 'reviews' => collect(), 'referrals' => collect(), 'draftReviews' => collect(),
            'clinics' => DB::table('clinics')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'city', 'area_code']),
            'eligibleClinicians' => DB::table('users')->join('practitioners', 'practitioners.user_id', '=', 'users.id')
                ->where('users.role', UserRole::Clinician->value)->where('users.is_active', true)
                ->where('practitioners.credential_status', 'verified')
                ->where(fn ($q) => $q->whereNull('practitioners.expires_at')->orWhere('practitioners.expires_at', '>', now()))
                ->orderBy('users.name')->get(['users.id', 'users.name']),
            'assignments' => DB::table('case_assignments')->join('users', 'users.id', '=', 'case_assignments.assignee_user_id')
                ->where('case_assignments.case_id', $case->id)->whereNull('case_assignments.released_at')
                ->get(['case_assignments.assignee_user_id', 'case_assignments.purpose', 'users.name', 'users.role']),
            'shared' => [
                'patient_name' => $case->patient_name,
                'patient_mobile' => $case->patient_mobile,
                'tehran_area' => $case->tehran_area,
                'preferred_contact_time' => $case->preferred_contact_time,
                'contact_reason' => $case->contact_reason,
                'budget_band' => $case->budget_band,
            ],
        ];
    }

    private function clinicianData(Request $request, PatientCase $case): array
    {
        $documents = DB::table('clinical_documents')
            ->join('consent_events', 'consent_events.id', '=', 'clinical_documents.consent_event_id')
            ->where('clinical_documents.case_id', $case->id)
            ->where('clinical_documents.status', 'approved')
            ->where('consent_events.decision', 'accepted')->whereNull('consent_events.revoked_at')
            ->whereNull('clinical_documents.deleted_at')->orderByDesc('clinical_documents.created_at')
            ->get(['clinical_documents.id', 'clinical_documents.original_name', 'clinical_documents.status', 'clinical_documents.created_at']);

        return [
            'documents' => $documents, 'reviews' => collect(), 'referrals' => collect(), 'clinics' => collect(), 'assignments' => collect(), 'eligibleClinicians' => collect(), 'shared' => [],
            'draftReviews' => DB::table('review_revisions')->where('case_id', $case->id)
                ->where('clinician_user_id', $request->user()->id)->whereNull('signed_at')
                ->orderByDesc('created_at')->get(['id', 'revision_number', 'clinical_document_id', 'created_at']),
        ];
    }

    private function clinicRepresentativeData(Request $request, PatientCase $case): array
    {
        $grant = DB::table('referral_grants')
            ->join('clinics', 'clinics.id', '=', 'referral_grants.clinic_id')
            ->join('clinic_memberships', 'clinic_memberships.clinic_id', '=', 'referral_grants.clinic_id')
            ->join('consent_events', 'consent_events.id', '=', 'referral_grants.consent_event_id')
            ->where('referral_grants.case_id', $case->id)
            ->where('clinics.is_active', true)
            ->where('clinic_memberships.user_id', $request->user()->id)
            ->where('clinic_memberships.active_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('clinic_memberships.active_until')->orWhere('clinic_memberships.active_until', '>', now()))
            ->whereNull('referral_grants.revoked_at')->whereNotNull('referral_grants.expires_at')->where('referral_grants.expires_at', '>', now())
            ->where('consent_events.decision', 'accepted')->whereNull('consent_events.revoked_at')
            ->first(['referral_grants.scope', 'referral_grants.expires_at']);
        abort_unless($grant, 404);
        $scope = json_decode($grant->scope, true) ?: [];

        return [
            'documents' => collect(), 'reviews' => collect(), 'referrals' => collect(), 'clinics' => collect(), 'assignments' => collect(), 'draftReviews' => collect(), 'eligibleClinicians' => collect(),
            'shared' => [
                'patient_name' => in_array('contact', $scope, true) ? $case->patient_name : null,
                'patient_mobile' => in_array('contact', $scope, true) ? $case->patient_mobile : null,
                'service_type' => in_array('service_need', $scope, true) ? $case->service_type->value : null,
                'tehran_area' => in_array('service_need', $scope, true) ? $case->tehran_area : null,
                'budget_band' => in_array('service_need', $scope, true) ? $case->budget_band : null,
                'grant_expires_at' => $grant->expires_at,
            ],
        ];
    }
}
