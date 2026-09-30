<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CoordinationTask;
use App\Models\HomeServiceRequest;
use App\Models\PatientCase;
use App\Models\ReferralProposal;
use App\Models\SupportConversation;
use App\Support\WorkspaceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class OperationsAnalyticsController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        $user = $request->user();
        abort_unless(
            $user?->is_active
            && $user->role === UserRole::Owner
            && ! $request->session()->get('panel_demo', false),
            403,
        );

        $range = (string) $request->query('range', '30d');
        $days = match ($range) {
            '90d' => 90,
            '1y' => 365,
            default => 30,
        };
        $since = now()->subDays($days);

        $caseBase = PatientCase::query()->where('created_at', '>=', $since);
        $cases = (clone $caseBase)->get(['status', 'service_type', 'created_at']);
        $support = SupportConversation::query()
            ->where('opened_at', '>=', $since)
            ->get(['status', 'priority', 'opened_at', 'first_response_at', 'resolved_at']);
        $referrals = ReferralProposal::query()
            ->where('proposed_at', '>=', $since)
            ->get(['status', 'proposed_at', 'decided_at', 'withdrawn_at']);
        $home = HomeServiceRequest::query()
            ->where('created_at', '>=', $since)
            ->get(['status', 'created_at', 'completed_at', 'cancelled_at']);
        $tasks = CoordinationTask::query()
            ->where('created_at', '>=', $since)
            ->get(['status', 'created_at', 'due_at']);

        $firstResponseMinutes = $support
            ->filter(fn ($row) => $row->first_response_at !== null)
            ->map(fn ($row) => $row->opened_at->diffInMinutes($row->first_response_at))
            ->values();

        return view('panel.analytics.index', [
            ...WorkspaceView::data($request, 'analytics'),
            'range' => $range,
            'days' => $days,
            'summary' => [
                'cases' => $cases->count(),
                'support_opened' => $support->count(),
                'support_open' => $support->whereIn('status', ['open', 'in_progress', 'awaiting_patient', 'reopened'])->count(),
                'avg_first_response_minutes' => $firstResponseMinutes->isEmpty() ? null : (int) round($firstResponseMinutes->avg()),
                'referrals' => $referrals->count(),
                'referrals_accepted' => $referrals->where('status', 'accepted')->count(),
                'home_requests' => $home->count(),
                'home_completed' => $home->where('status', 'completed')->count(),
                'tasks_open' => $tasks->whereIn('status', ['open', 'in_progress'])->count(),
                'tasks_overdue' => $tasks->filter(fn ($row) => in_array($row->status, ['open', 'in_progress'], true) && $row->due_at?->isPast())->count(),
            ],
            'caseStatuses' => $this->countBy($cases, fn ($row) => $this->value($row->status)),
            'serviceMix' => $this->countBy($cases, fn ($row) => $this->value($row->service_type)),
            'supportStatuses' => $this->countBy($support, fn ($row) => $this->value($row->status)),
            'referralStatuses' => $this->countBy($referrals, fn ($row) => $this->value($row->status)),
            'homeStatuses' => $this->countBy($home, fn ($row) => $this->value($row->status)),
            'taskStatuses' => $this->countBy($tasks, fn ($row) => $this->value($row->status)),
            'caseTrend' => $this->weeklyTrend($cases, 'created_at'),
            'supportTrend' => $this->weeklyTrend($support, 'opened_at'),
        ])->with('locale', $locale);
    }

    private function value(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    /**
     * @return Collection<string,int>
     */
    private function countBy(Collection $rows, callable $key): Collection
    {
        return $rows
            ->groupBy($key)
            ->map(fn (Collection $items) => $items->count())
            ->sortDesc();
    }

    /**
     * @return Collection<int,array{label:string,count:int}>
     */
    private function weeklyTrend(Collection $rows, string $field): Collection
    {
        return $rows
            ->groupBy(fn ($row) => optional($row->{$field})->startOfWeek()->format('Y-m-d'))
            ->filter(fn ($items, $week) => $week !== '')
            ->map(fn (Collection $items, string $week) => ['label' => $week, 'count' => $items->count()])
            ->sortBy('label')
            ->values();
    }
}
