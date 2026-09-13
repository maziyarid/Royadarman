<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\UserRole;
use App\Models\PatientCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PanelController extends Controller
{
    public function __invoke(Request $request, string $locale): View
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);
        $isDemo = (bool) $request->session()->get('panel_demo', false);

        [$metrics, $cases, $panelKey] = match ($user->role) {
            UserRole::Patient => $this->patientPanel((int) $user->id, $isDemo),
            UserRole::Coordinator => $this->coordinatorPanel((int) $user->id, $isDemo),
            UserRole::Clinician => $this->clinicianPanel((int) $user->id, $isDemo),
            UserRole::ClinicRepresentative => $this->clinicRepresentativePanel((int) $user->id, $isDemo),
            UserRole::Owner => $this->ownerPanel($isDemo),
            UserRole::TechnicalAdministrator => $this->technicalPanel($isDemo),
        };

        return view('panel.dashboard', [
            'panelKey' => $panelKey,
            'metrics' => $metrics,
            'cases' => $cases,
            'canManageMarketing' => ! $isDemo && $user->role === UserRole::Owner,
            'isDemo' => $isDemo,
        ])->with('locale', $locale);
    }

    private function patientPanel(int $userId, bool $isDemo): array
    {
        $cases = PatientCase::query()
            ->where('patient_user_id', $userId)
            ->when($isDemo, fn ($query) => $query->where('public_reference', 'like', 'TEST-DEMO-%'))
            ->latest()
            ->limit(30)
            ->get(['id', 'public_reference', 'service_type', 'status', 'submitted_at', 'updated_at']);

        return [[
            'cases' => $cases->count(),
            'active' => $cases->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'documents' => DB::table('clinical_documents')
                ->join('patient_cases', 'patient_cases.id', '=', 'clinical_documents.case_id')
                ->where('patient_cases.patient_user_id', $userId)
                ->when($isDemo, fn ($query) => $query->where('patient_cases.public_reference', 'like', 'TEST-DEMO-%'))
                ->whereNull('clinical_documents.deleted_at')
                ->count(),
        ], $cases, 'patient'];
    }

    private function coordinatorPanel(int $userId, bool $isDemo): array
    {
        $cases = DB::table('patient_cases')
            ->join('case_assignments', 'case_assignments.case_id', '=', 'patient_cases.id')
            ->where('case_assignments.assignee_user_id', $userId)
            ->where('case_assignments.purpose', 'coordination')
            ->whereNull('case_assignments.released_at')
            ->when($isDemo, fn ($query) => $query->where('patient_cases.public_reference', 'like', 'TEST-DEMO-%'))
            ->orderByDesc('patient_cases.updated_at')
            ->limit(50)
            ->get([
                'patient_cases.id', 'patient_cases.public_reference', 'patient_cases.service_type',
                'patient_cases.status', 'patient_cases.priority', 'patient_cases.submitted_at', 'patient_cases.updated_at',
            ]);

        return [[
            'assigned' => $cases->count(),
            'awaiting' => $cases->where('status', 'awaiting_patient')->count(),
            'urgent' => $cases->where('priority', 'urgent')->count(),
        ], $cases, 'coordinator'];
    }

    private function clinicianPanel(int $userId, bool $isDemo): array
    {
        $cases = DB::table('patient_cases')
            ->join('case_assignments', 'case_assignments.case_id', '=', 'patient_cases.id')
            ->where('case_assignments.assignee_user_id', $userId)
            ->where('case_assignments.purpose', 'clinical_review')
            ->whereNull('case_assignments.released_at')
            ->when($isDemo, fn ($query) => $query->where('patient_cases.public_reference', 'like', 'TEST-DEMO-%'))
            ->orderByDesc('patient_cases.updated_at')
            ->limit(50)
            ->get([
                'patient_cases.id', 'patient_cases.public_reference', 'patient_cases.service_type',
                'patient_cases.status', 'patient_cases.submitted_at', 'patient_cases.updated_at',
            ]);

        $reviewCount = function (bool $signed) use ($userId, $isDemo): int {
            return DB::table('review_revisions')
                ->join('patient_cases', 'patient_cases.id', '=', 'review_revisions.case_id')
                ->where('review_revisions.clinician_user_id', $userId)
                ->when($isDemo, fn ($query) => $query->where('patient_cases.public_reference', 'like', 'TEST-DEMO-%'))
                ->when($signed,
                    fn ($query) => $query->whereNotNull('review_revisions.signed_at'),
                    fn ($query) => $query->whereNull('review_revisions.signed_at'),
                )
                ->count();
        };

        return [[
            'assigned' => $cases->count(),
            'draft_reviews' => $reviewCount(false),
            'published_reviews' => $reviewCount(true),
        ], $cases, 'clinician'];
    }

    private function clinicRepresentativePanel(int $userId, bool $isDemo): array
    {
        $cases = DB::table('patient_cases')
            ->join('referral_grants', 'referral_grants.case_id', '=', 'patient_cases.id')
            ->join('clinics', 'clinics.id', '=', 'referral_grants.clinic_id')
            ->join('clinic_memberships', 'clinic_memberships.clinic_id', '=', 'referral_grants.clinic_id')
            ->join('consent_events', 'consent_events.id', '=', 'referral_grants.consent_event_id')
            ->where('clinics.is_active', true)
            ->where('clinic_memberships.user_id', $userId)
            ->where('clinic_memberships.active_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('clinic_memberships.active_until')->orWhere('clinic_memberships.active_until', '>', now()))
            ->whereNull('referral_grants.revoked_at')
            ->whereNotNull('referral_grants.expires_at')
            ->where('referral_grants.expires_at', '>', now())
            ->where('consent_events.decision', 'accepted')
            ->whereNull('consent_events.revoked_at')
            ->when($isDemo, fn ($query) => $query->where('patient_cases.public_reference', 'like', 'TEST-DEMO-%'))
            ->orderByDesc('referral_grants.granted_at')
            ->limit(50)
            ->get([
                'patient_cases.id', 'patient_cases.public_reference', 'patient_cases.service_type',
                'patient_cases.status', 'referral_grants.expires_at', 'patient_cases.updated_at',
            ]);

        return [[
            'active_referrals' => $cases->count(),
            'expiring_soon' => $cases->filter(fn ($case) => now()->diffInHours($case->expires_at, false) <= 48)->count(),
        ], $cases, 'clinic_rep'];
    }

    private function ownerPanel(bool $isDemo): array
    {
        if ($isDemo) {
            return [[
                'open_cases' => DB::table('patient_cases')
                    ->where('public_reference', 'like', 'TEST-DEMO-%')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count(),
                'active_clinics' => DB::table('clinics')
                    ->where('name', 'TEST Demo Clinic')
                    ->where('area_code', 'test-demo')
                    ->where('is_active', true)
                    ->count(),
                'verified_clinicians' => DB::table('practitioners')
                    ->join('users', 'users.id', '=', 'practitioners.user_id')
                    ->where('users.email', 'demo-clinician@royadarman.invalid')
                    ->where('practitioners.credential_status', 'verified')
                    ->where(fn ($q) => $q->whereNull('practitioners.expires_at')->orWhere('practitioners.expires_at', '>', now()))
                    ->count(),
                'notification_failures' => 0,
            ], collect(), 'owner'];
        }

        return [[
            'open_cases' => DB::table('patient_cases')->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'active_clinics' => DB::table('clinics')->where('is_active', true)->count(),
            'verified_clinicians' => DB::table('practitioners')->where('credential_status', 'verified')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'notification_failures' => DB::table('notification_deliveries')->where('status', 'failed')->count(),
        ], collect(), 'owner'];
    }

    private function technicalPanel(bool $isDemo): array
    {
        if ($isDemo) {
            return [[
                'queued_jobs' => 0,
                'failed_jobs' => 0,
                'pending_outbox' => 0,
                'scan_failures' => 0,
                'pending_retention' => 0,
            ], collect(), 'tech_admin'];
        }

        return [[
            'queued_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'pending_outbox' => DB::table('outbox_events')->whereNull('processed_at')->count(),
            'scan_failures' => DB::table('scan_attempts')->whereNotNull('error_code')->count(),
            'pending_retention' => DB::table('retention_jobs')->where('status', 'pending')->count(),
        ], collect(), 'tech_admin'];
    }
}
