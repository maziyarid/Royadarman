<?php

namespace App\Domain\Dashboard;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\HomeServiceStatus;
use App\Domain\Cases\Enums\ServiceType;
use App\Domain\CMS\Enums\PostStatus;
use App\Domain\Identity\Enums\CredentialStatus;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Support\Enums\ConversationStatus;
use App\Models\AuditEvent;
use App\Models\Clinic;
use App\Models\Cms\Post;
use App\Models\ConsentEvent;
use App\Models\HomeServiceRequest;
use App\Models\OutboxEvent;
use App\Models\PatientCase;
use App\Models\Practitioner;
use App\Models\ReferralGrant;
use App\Models\ReferralProposal;
use App\Models\ReviewRevision;
use App\Models\SupportConversation;
use App\Models\User;
use App\Support\PanelDemoRegistry;
use App\Support\WaitClock;
use Illuminate\Support\Facades\Schema;

final class DashboardService
{
    public function build(User $user, bool $isDemo = false): array
    {
        return match ($user->role) {
            UserRole::Patient => $this->patient($user, $isDemo),
            UserRole::Clinician => $this->clinician($user, $isDemo),
            UserRole::ClinicRepresentative => $this->clinicRepresentative($user, $isDemo),
            UserRole::Coordinator => $this->coordinator($user, $isDemo),
            UserRole::Owner => $this->businessAdmin($user, $isDemo),
            UserRole::TechnicalAdministrator => $this->technicalAdmin($user, $isDemo),
        };
    }

    private function demoCases($query)
    {
        return $query->where('public_reference', 'like', PanelDemoRegistry::CASE_REFERENCE_PREFIX.'%');
    }

    private function patient(User $user, bool $isDemo): array
    {
        $cases = $user->cases()
            ->with(['documents', 'reviewRevisions.publicationEvents'])
            ->when($isDemo, fn ($q) => $this->demoCases($q))
            ->latest()->limit(20)->get();
        $openReferrals = ReferralProposal::query()
            ->whereHas('case', fn ($q) => $q->where('patient_user_id', $user->id)
                ->when($isDemo, fn ($case) => $this->demoCases($case)))
            ->where('status', 'proposed')
            ->count();
        $support = $user->supportConversations()
            ->when($isDemo, fn ($q) => $q->where(function ($inner): void {
                $inner->whereNull('case_id')->orWhereHas('case', fn ($case) => $this->demoCases($case));
            }))
            ->latest()->limit(5)->get();

        return [
            'role' => UserRole::Patient->value,
            'cases' => $cases->map(fn ($c) => [
                'id' => $c->id,
                'public_reference' => $c->public_reference,
                'status' => $c->status instanceof CaseStatus
                    ? $c->status->value : (string) $c->status,
                'service_type' => $c->service_type instanceof ServiceType
                    ? $c->service_type->value : (string) $c->service_type,
                'created_at' => $c->created_at,
                'documents_count' => $c->documents->count(),
                'has_published_review' => $c->reviewRevisions->contains(fn ($r) => $r->isPublished()),
                ...WaitClock::waiting($c->updated_at ?? $c->created_at),
            ]),
            'open_referral_proposals' => $openReferrals,
            'home_service_requests' => $user->homeServiceRequests()
                ->when($isDemo, fn ($q) => $q->whereHas('case', fn ($case) => $this->demoCases($case)))
                ->latest()->limit(5)->get(['id', 'status', 'tehran_area', 'created_at'])
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'status' => $h->status instanceof HomeServiceStatus
                        ? $h->status->value : (string) $h->status,
                    'tehran_area' => $h->tehran_area,
                    'created_at' => $h->created_at,
                ]),
            'support_conversations' => $support->map(fn ($s) => [
                'id' => $s->id,
                'subject' => $s->subject,
                'status' => $s->status instanceof ConversationStatus
                    ? $s->status->value : (string) $s->status,
                'updated_at' => $s->updated_at,
            ]),
        ];
    }

    private function clinician(User $user, bool $isDemo): array
    {
        $practitioner = $user->practitioner;
        $credentialValid = $practitioner && $practitioner->isCurrentlyVerified();

        $assignedReviews = ReviewRevision::query()
            ->where('clinician_user_id', $user->id)
            ->whereDoesntHave('supersededBy')
            ->when($isDemo, fn ($q) => $q->whereHas('case', fn ($case) => $this->demoCases($case)))
            ->with(['case:id,status,public_reference', 'publicationEvents'])
            ->latest()->limit(20)->get();

        $openDrafts = ReviewRevision::query()
            ->where('clinician_user_id', $user->id)
            ->whereDoesntHave('supersededBy')
            ->when($isDemo, fn ($q) => $q->whereHas('case', fn ($case) => $this->demoCases($case)))
            ->whereDoesntHave('publicationEvents', fn ($q) => $q->where('event', 'published'))
            ->count();

        return [
            'role' => UserRole::Clinician->value,
            'credential_status' => $practitioner?->credential_status instanceof CredentialStatus
                ? $practitioner->credential_status->value : (string) ($practitioner?->credential_status ?? ''),
            'credential_valid' => $credentialValid,
            'can_publish' => (bool) $credentialValid,
            'assigned_reviews' => $assignedReviews->map(fn ($r) => [
                'id' => $r->id,
                'case_id' => $r->case_id,
                'public_reference' => optional($r->case)->public_reference,
                'case_status' => optional($r->case)->status instanceof CaseStatus
                    ? $r->case->status->value : (string) optional($r->case)->status,
                'is_published' => $r->isPublished(),
                'updated_at' => $r->updated_at,
                ...WaitClock::waiting($r->updated_at),
            ]),
            'open_drafts_count' => $openDrafts,
        ];
    }

    private function clinicRepresentative(User $user, bool $isDemo): array
    {
        $clinicIds = $user->clinicMemberships()
            ->whereNull('active_until')
            ->orWhere('active_until', '>', now())
            ->pluck('clinic_id');

        $clinics = Clinic::query()->whereIn('id', $clinicIds)
            ->when($isDemo, fn ($q) => $q->where('synthetic_demo_key', PanelDemoRegistry::CLINIC_DEMO_KEY))
            ->get(['id', 'name', 'is_active']);
        $grants = ReferralGrant::query()
            ->whereIn('clinic_id', $clinicIds)
            ->when($isDemo, fn ($q) => $q->whereHas('case', fn ($case) => $this->demoCases($case)))
            ->whereNull('revoked_at')
            ->with('case:id,status,patient_name,public_reference')
            ->latest('granted_at')->limit(20)->get();

        return [
            'role' => UserRole::ClinicRepresentative->value,
            'clinics' => $clinics->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'is_active' => $c->is_active,
            ]),
            'active_referral_grants' => $grants->map(fn ($g) => [
                'id' => $g->id,
                'case_id' => $g->case_id,
                'public_reference' => optional($g->case)->public_reference,
                'case_status' => optional($g->case)->status instanceof CaseStatus
                    ? $g->case->status->value : (string) optional($g->case)->status,
                'granted_at' => $g->granted_at,
                'expires_at' => $g->expires_at,
                'expires_in_minutes' => WaitClock::remainingMinutes($g->expires_at),
                'expiry_band' => WaitClock::expiryBand(WaitClock::remainingMinutes($g->expires_at)),
            ]),
            'pending_proposals' => ReferralProposal::query()
                ->whereIn('clinic_id', $clinicIds)
                ->when($isDemo, fn ($q) => $q->whereHas('case', fn ($case) => $this->demoCases($case)))
                ->where('status', 'proposed')->count(),
        ];
    }

    private function coordinator(User $user, bool $isDemo): array
    {
        $myQueue = $user->coordinatedCases()
            ->with(['documents', 'reviewRevisions.publicationEvents'])
            ->when($isDemo, fn ($q) => $this->demoCases($q))
            ->latest()->limit(20)->get(['id', 'public_reference', 'status', 'service_type', 'updated_at']);

        $awaitingPatient = $user->coordinatedCases()
            ->when($isDemo, fn ($q) => $this->demoCases($q))
            ->where('status', CaseStatus::AwaitingPatient->value)
            ->count();

        $openSupport = SupportConversation::query()
            ->where(function ($q) use ($user): void {
                $q->where('assignee_user_id', $user->id)
                    ->orWhereNull('assignee_user_id');
            })
            ->when($isDemo, fn ($q) => $q->where(function ($inner): void {
                $inner->whereNull('case_id')->orWhereHas('case', fn ($case) => $this->demoCases($case));
            }))
            ->where('status', 'open')
            ->count();

        return [
            'role' => UserRole::Coordinator->value,
            'case_queue' => $myQueue->map(fn ($c) => [
                'id' => $c->id,
                'public_reference' => $c->public_reference,
                'status' => $c->status instanceof CaseStatus
                    ? $c->status->value : (string) $c->status,
                'service_type' => $c->service_type instanceof ServiceType
                    ? $c->service_type->value : (string) $c->service_type,
                'documents_count' => $c->documents->count(),
                'has_published_review' => $c->reviewRevisions->contains(fn ($r) => $r->isPublished()),
                'updated_at' => $c->updated_at,
                ...WaitClock::waiting($c->updated_at ?? $c->created_at),
            ]),
            'awaiting_patient_count' => $awaitingPatient,
            'open_unassigned_support' => $openSupport,
            'home_service_pending' => HomeServiceRequest::query()
                ->when($isDemo, fn ($q) => $q->whereHas('case', fn ($case) => $this->demoCases($case)))
                ->whereIn('status', ['requested', 'area_verified', 'coordinator_review'])
                ->count(),
        ];
    }

    private function businessAdmin(User $user, bool $isDemo): array
    {
        $caseQuery = PatientCase::query()->when($isDemo, fn ($q) => $this->demoCases($q));
        $caseStatusCounts = (clone $caseQuery)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $clinicCounts = Clinic::query()
            ->when($isDemo, fn ($q) => $q->where('synthetic_demo_key', PanelDemoRegistry::CLINIC_DEMO_KEY))
            ->toBase()
            ->selectRaw(
                'count(*) as total, sum(case when is_active = 1 then 1 else 0 end) as active'
            )->first();

        $postCounts = Post::query()
            ->toBase()
            ->selectRaw(
                'sum(case when status = ? then 1 else 0 end) as published,'
                .' sum(case when status = ? then 1 else 0 end) as review',
                [PostStatus::Published->value, PostStatus::InReview->value]
            )->first();

        return [
            'role' => UserRole::Owner->value,
            'case_status_counts' => $caseStatusCounts,
            'total_cases' => $caseStatusCounts->sum(),
            'total_clinics' => (int) $clinicCounts->total,
            'active_clinics' => (int) $clinicCounts->active,
            'verified_practitioners' => Practitioner::query()
                ->where('credential_status', 'verified')->count(),
            'open_support' => SupportConversation::query()
                ->when($isDemo, fn ($q) => $q->where(function ($inner): void {
                    $inner->whereNull('case_id')->orWhereHas('case', fn ($case) => $this->demoCases($case));
                }))
                ->where('status', 'open')->count(),
            'published_posts' => (int) $postCounts->published,
            'pending_review_posts' => (int) $postCounts->review,
        ];
    }

    private function technicalAdmin(User $user, bool $isDemo): array
    {
        if ($isDemo) {
            return [
                'role' => UserRole::TechnicalAdministrator->value,
                'outbox' => ['pending' => 0, 'failed' => 0],
                'queued_jobs' => 0,
                'failed_jobs' => 0,
                'scanner_enabled' => false,
                'intake_enabled' => false,
                'panel_demo_access' => true,
                'retention_configured' => false,
                'sms_provider_configured' => false,
                'audit_events_24h' => 0,
                'consent_records' => 0,
                'recent_audit' => [],
            ];
        }
        $pendingOutbox = OutboxEvent::query()->whereNull('processed_at')->count();
        $failedDeliveries = \DB::table('notification_deliveries')->where('status', 'failed')->count();
        $queuedJobs = Schema::hasTable('jobs') ? \DB::table('jobs')->count() : 0;
        $failedJobs = Schema::hasTable('failed_jobs') ? \DB::table('failed_jobs')->count() : 0;

        return [
            'role' => UserRole::TechnicalAdministrator->value,
            'outbox' => [
                'pending' => $pendingOutbox,
                'failed' => $failedDeliveries,
            ],
            'queued_jobs' => $queuedJobs,
            'failed_jobs' => $failedJobs,
            'scanner_enabled' => (bool) config('royadarman.opg.scanner.enabled'),
            'intake_enabled' => (bool) config('royadarman.intake_enabled'),
            'panel_demo_access' => (bool) config('royadarman.panel_demo_access'),
            'retention_configured' => config('royadarman.retention.document_days') !== null && config('royadarman.retention.document_days') !== '',
            'sms_provider_configured' => trim((string) config('royadarman.sms.provider')) !== '',
            'audit_events_24h' => AuditEvent::query()
                ->where('created_at', '>=', now()->subDay())->count(),
            'consent_records' => ConsentEvent::query()->count(),
            'recent_audit' => AuditEvent::query()
                ->latest()->limit(10)->get(['id', 'action', 'actor_user_id', 'created_at'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'action' => $a->action,
                    'actor_user_id' => $a->actor_user_id,
                    'created_at' => $a->created_at,
                ]),
        ];
    }
}
