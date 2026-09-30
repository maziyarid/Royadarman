<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Cms\Post;
use App\Models\PatientCase;
use App\Models\SupportConversation;
use App\Models\User;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class WorkspaceSearchController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        $user = $request->user();

        abort_unless($user?->is_active && ! $request->session()->get('panel_demo', false), 403);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = trim((string) ($data['q'] ?? ''));
        $results = collect();

        if ($query !== '') {
            $results = $results
                ->concat($this->navigationResults($user->role, $locale, $query))
                ->concat($this->caseResults($user, $locale, $query))
                ->concat($this->supportResults($user, $locale, $query))
                ->concat($this->contentResults($user, $query))
                ->take(30)
                ->values();
        }

        return view('panel.search.index', [
            ...WorkspaceView::data($request, 'search'),
            'query' => $query,
            'results' => $results,
        ])->with('locale', $locale);
    }

    private function navigationResults(UserRole $role, string $locale, string $query): Collection
    {
        $items = [
            ['key' => 'panel.nav.overview', 'route' => route('panel', ['locale' => $locale])],
            ['key' => 'ui.dashboard.title', 'route' => route('dashboard', ['locale' => $locale])],
            ['key' => 'panel.nav.profile', 'route' => route('panel.profile', ['locale' => $locale])],
        ];

        if (in_array($role, [UserRole::Patient, UserRole::Coordinator, UserRole::Owner, UserRole::TechnicalAdministrator], true)) {
            $items[] = ['key' => 'panel.nav.support', 'route' => route('panel.support.index', ['locale' => $locale])];
        }
        if (in_array($role, [UserRole::Coordinator, UserRole::Clinician], true)) {
            $items[] = ['key' => 'panel.nav.cases', 'route' => route('panel.cases.index', ['locale' => $locale])];
        }
        if ($role === UserRole::Coordinator) {
            $items[] = ['key' => 'panel.nav.tasks', 'route' => route('panel.tasks.index', ['locale' => $locale])];
            $items[] = ['key' => 'panel.nav.calendar', 'route' => route('panel.calendar.index', ['locale' => $locale])];
        }
        if (in_array($role, [UserRole::Patient, UserRole::Coordinator, UserRole::ClinicRepresentative], true)) {
            $items[] = ['key' => 'panel.nav.home_service', 'route' => route('panel.home-service.index', ['locale' => $locale])];
        }
        if ($role === UserRole::ClinicRepresentative) {
            $items[] = ['key' => 'panel.nav.calendar', 'route' => route('panel.calendar.index', ['locale' => $locale])];
        }
        if ($role === UserRole::Patient) {
            $items[] = ['key' => 'request.title', 'route' => route('patient.request.create', ['locale' => $locale])];
        }
        if ($role === UserRole::Owner) {
            $items[] = ['key' => 'panel.nav.analytics', 'route' => route('panel.analytics.index', ['locale' => $locale])];
            $items[] = ['key' => 'panel.nav.administrators', 'route' => route('administrators.index', ['locale' => $locale])];
            $items[] = ['key' => 'panel.marketing', 'route' => route('marketing.index', ['locale' => $locale])];
            $items[] = ['key' => 'network.title', 'route' => route('network.index', ['locale' => $locale])];
        }
        if (in_array($role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)) {
            $items[] = ['key' => 'panel.nav.integrations', 'route' => route('integrations.index', ['locale' => $locale])];
            $items[] = ['key' => 'panel.nav.launch_readiness', 'route' => route('panel.launch-readiness.index', ['locale' => $locale])];
            $items[] = ['key' => 'panel.nav.deliveries', 'route' => route('panel.deliveries.index', ['locale' => $locale])];
            $items[] = ['key' => 'panel.nav.policies', 'route' => route('panel.policies.index', ['locale' => $locale])];
            $items[] = ['key' => 'ui.admin.title', 'route' => route('admin.cms.dashboard')];
        }

        $needle = Str::lower($query);

        return collect($items)
            ->map(fn (array $item) => [
                'type' => 'navigation',
                'title' => __($item['key']),
                'meta' => __('panel.search.navigation'),
                'url' => $item['route'],
            ])
            ->filter(fn (array $item) => Str::contains(Str::lower($item['title']), $needle))
            ->values();
    }

    private function caseResults(User $user, string $locale, string $query): Collection
    {
        $role = $user->role;
        $caseQuery = PatientCase::query()
            ->select(['patient_cases.id', 'patient_cases.public_reference', 'patient_cases.status', 'patient_cases.service_type', 'patient_cases.updated_at'])
            ->where('patient_cases.public_reference', 'like', '%'.$query.'%');

        if ($role === UserRole::Patient) {
            $caseQuery->where('patient_cases.patient_user_id', $user->id);
        } elseif ($role === UserRole::Coordinator) {
            $caseQuery
                ->join('case_assignments', 'case_assignments.case_id', '=', 'patient_cases.id')
                ->where('case_assignments.assignee_user_id', $user->id)
                ->where('case_assignments.purpose', 'coordination')
                ->whereNull('case_assignments.released_at')
                ->distinct();
        } elseif ($role === UserRole::Clinician) {
            $practitioner = $user->practitioner;
            if (! $practitioner?->isCurrentlyVerified()) {
                return collect();
            }

            $caseQuery
                ->join('case_assignments', 'case_assignments.case_id', '=', 'patient_cases.id')
                ->where('case_assignments.assignee_user_id', $user->id)
                ->where('case_assignments.purpose', 'clinical_review')
                ->whereNull('case_assignments.released_at')
                ->distinct();
        } else {
            return collect();
        }

        return $caseQuery
            ->latest('patient_cases.updated_at')
            ->limit(12)
            ->get()
            ->map(function (PatientCase $case) use ($locale): array {
                $status = $case->status instanceof \BackedEnum ? $case->status->value : (string) $case->status;
                $service = $case->service_type instanceof \BackedEnum ? $case->service_type->value : (string) $case->service_type;

                return [
                    'type' => 'case',
                    'title' => $case->public_reference,
                    'meta' => __('ui.dashboard.service.'.$service).' · '.__('ui.dashboard.status.'.$status),
                    'url' => route('panel.case', ['locale' => $locale, 'case' => $case->id]),
                ];
            });
    }

    private function supportResults(User $user, string $locale, string $query): Collection
    {
        if (! in_array($user->role, [UserRole::Patient, UserRole::Coordinator], true)) {
            return collect();
        }

        $support = SupportConversation::query()
            ->select(['id', 'subject', 'status', 'category', 'updated_at', 'patient_user_id', 'assignee_user_id'])
            ->where(function ($q) use ($query): void {
                $q->where('subject', 'like', '%'.$query.'%')
                    ->orWhereHas('case', fn ($case) => $case->where('public_reference', 'like', '%'.$query.'%'));
            });

        if ($user->role === UserRole::Patient) {
            $support->where('patient_user_id', $user->id);
        } else {
            $support->where(fn ($q) => $q->whereNull('assignee_user_id')->orWhere('assignee_user_id', $user->id));
        }

        return $support
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(function (SupportConversation $conversation) use ($locale): array {
                $status = $conversation->status instanceof \BackedEnum ? $conversation->status->value : (string) $conversation->status;
                $category = $conversation->category instanceof \BackedEnum ? $conversation->category->value : (string) $conversation->category;

                return [
                    'type' => 'support',
                    'title' => $conversation->subject ?: __('panel.support.untitled'),
                    'meta' => __('panel.support.category_values.'.$category).' · '.__('panel.support.status.'.$status),
                    'url' => route('panel.support.show', ['locale' => $locale, 'conversation' => $conversation->id]),
                ];
            });
    }

    private function contentResults(User $user, string $query): Collection
    {
        if (! in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)) {
            return collect();
        }

        return Post::query()
            ->with('translations')
            ->whereHas('translations', fn ($q) => $q->where('title', 'like', '%'.$query.'%'))
            ->latest('updated_at')
            ->limit(12)
            ->get()
            ->map(function (Post $post): array {
                $translation = $post->translations->firstWhere('locale', app()->getLocale()) ?? $post->translations->first();

                return [
                    'type' => 'content',
                    'title' => $translation?->title ?? __('panel.search.untitled_content'),
                    'meta' => __('panel.search.cms_content'),
                    'url' => route('admin.cms.posts.show', $post),
                ];
            });
    }
}
