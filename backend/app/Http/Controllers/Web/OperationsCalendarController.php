<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CoordinationTask;
use App\Models\HomeServiceRequest;
use App\Models\ReferralGrant;
use App\Domain\Scheduling\OperationsMonthWindow;
use App\Support\JalaliCalendar;
use App\Support\WorkspaceView;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class OperationsCalendarController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        $user = $request->user();

        abort_unless(
            $user?->is_active
            && $user->role === UserRole::Coordinator
            && ! $request->session()->get('panel_demo', false),
            403,
        );

        $timezone = config('royadarman.display_timezone');
        $jalaliMode = $locale === 'fa';
        $jalaliYear = null;
        $jalaliMonth = null;
        $serverWindow = null;

        if ($jalaliMode) {
            [$currentJalaliYear, $currentJalaliMonth] = JalaliCalendar::fromGregorian(
                (int) CarbonImmutable::now($timezone)->format('Y'),
                (int) CarbonImmutable::now($timezone)->format('m'),
                (int) CarbonImmutable::now($timezone)->format('d'),
            );
            $monthInput = (string) $request->query('jmonth', JalaliCalendar::monthKey($currentJalaliYear, $currentJalaliMonth));
            abort_unless((bool) preg_match('/^\d{4}-\d{2}$/', $monthInput), 422);
            [$jalaliYear, $jalaliMonth] = array_map('intval', explode('-', $monthInput));
            abort_unless($jalaliYear >= 1200 && $jalaliYear <= 1600 && $jalaliMonth >= 1 && $jalaliMonth <= 12, 422);
            $monthInput = JalaliCalendar::monthKey($jalaliYear, $jalaliMonth);
            $window = OperationsMonthWindow::jalali($jalaliYear, $jalaliMonth, (string) $timezone);
            $serverWindow = [
                'start_utc' => $window['start_utc'],
                'end_utc' => $window['end_utc'],
                'day_count' => $window['day_count'],
                'half_open' => true,
                'client_must_not_recompute_bounds' => true,
            ];
            $start = CarbonImmutable::create($window['start_year'], $window['start_month'], $window['start_day'], 0, 0, 0, $timezone);
            $endExclusive = CarbonImmutable::create($window['end_year'], $window['end_month'], $window['end_day'], 0, 0, 0, $timezone);
            $daysInMonth = $window['day_count'];
            $nextJalaliYear = $window['next_year'];
            $nextJalaliMonth = $window['next_month'];
            $monthLabel = JalaliCalendar::monthLabel($jalaliYear, $jalaliMonth);
            $previousMonth = $jalaliMonth === 1
                ? JalaliCalendar::monthKey($jalaliYear - 1, 12)
                : JalaliCalendar::monthKey($jalaliYear, $jalaliMonth - 1);
            $nextMonth = JalaliCalendar::monthKey($nextJalaliYear, $nextJalaliMonth);
        } else {
            $monthInput = (string) $request->query('month', CarbonImmutable::now($timezone)->format('Y-m'));
            abort_unless((bool) preg_match('/^\d{4}-\d{2}$/', $monthInput), 422);
            try {
                $month = CarbonImmutable::createFromFormat('!Y-m', $monthInput, $timezone);
            } catch (\Throwable) {
                abort(422);
            }
            abort_unless($month->format('Y-m') === $monthInput, 422);
            $start = $month->startOfMonth();
            $endExclusive = $start->addMonthNoOverflow();
            $daysInMonth = $month->daysInMonth;
            $monthLabel = $monthInput;
            $previousMonth = $month->subMonthNoOverflow()->format('Y-m');
            $nextMonth = $month->addMonthNoOverflow()->format('Y-m');
        }

        $startUtc = $start->utc();
        $endExclusiveUtc = $endExclusive->utc();

        $taskEvents = CoordinationTask::query()
            ->with('case:id,public_reference,current_coordinator_id')
            ->where('assignee_user_id', $user->id)
            ->whereHas('case', fn ($q) => $q->where('current_coordinator_id', $user->id))
            ->where('due_at', '>=', $startUtc)
            ->where('due_at', '<', $endExclusiveUtc)
            ->get()
            ->map(fn (CoordinationTask $task) => [
                'kind' => 'task',
                'at' => $task->due_at?->timezone(config('royadarman.display_timezone')),
                'title' => __('panel.calendar.types.task').' · '.$task->case?->public_reference,
                'meta' => __('panel.tasks.types.'.$task->task_type),
                'status' => $task->status,
                'url' => route('panel.tasks.index', ['locale' => $locale, 'status' => $task->status]),
            ]);

        $homeEvents = HomeServiceRequest::query()
            ->with('case:id,public_reference,current_coordinator_id')
            ->whereHas('case', fn ($q) => $q->where('current_coordinator_id', $user->id))
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>=', $startUtc)
            ->where('scheduled_for', '<', $endExclusiveUtc)
            ->get()
            ->map(fn (HomeServiceRequest $home) => [
                'kind' => 'home_service',
                'at' => $home->scheduled_for?->timezone(config('royadarman.display_timezone')),
                'title' => __('panel.calendar.types.home_service').' · '.$home->case?->public_reference,
                'meta' => __('ui.dashboard.home_status.'.($home->status instanceof \BackedEnum ? $home->status->value : $home->status)),
                'status' => $home->status instanceof \BackedEnum ? $home->status->value : (string) $home->status,
                'url' => route('panel.case', ['locale' => $locale, 'case' => $home->case_id]),
            ]);

        $referralEvents = ReferralGrant::query()
            ->with('case:id,public_reference,current_coordinator_id')
            ->whereHas('case', fn ($q) => $q->where('current_coordinator_id', $user->id))
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>=', $startUtc)
            ->where('expires_at', '<', $endExclusiveUtc)
            ->get()
            ->map(fn (ReferralGrant $grant) => [
                'kind' => 'referral_expiry',
                'at' => $grant->expires_at?->timezone(config('royadarman.display_timezone')),
                'title' => __('panel.calendar.types.referral_deadline').' · '.$grant->case?->public_reference,
                'meta' => __('panel.calendar.referral_deadline'),
                'status' => $grant->expires_at?->isPast() ? 'expired' : 'active',
                'url' => route('panel.case', ['locale' => $locale, 'case' => $grant->case_id]),
            ]);

        $events = $taskEvents
            ->concat($homeEvents)
            ->concat($referralEvents)
            ->filter(fn ($event) => $event['at'] !== null)
            ->sortBy('at')
            ->values();

        if ($jalaliMode) {
            $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($jalaliYear, $jalaliMonth, $events, $timezone): array {
                [$gy, $gm, $gd] = JalaliCalendar::toGregorian($jalaliYear, $jalaliMonth, $day);
                $date = CarbonImmutable::create($gy, $gm, $gd, 0, 0, 0, $timezone);
                $dateKey = JalaliCalendar::key($jalaliYear, $jalaliMonth, $day);
                return [
                    'date' => $date,
                    'key' => $dateKey,
                    'day_number' => JalaliCalendar::toPersianDigits((string) $day),
                    'weekday' => JalaliCalendar::WEEKDAYS[($date->dayOfWeek + 1) % 7],
                    'friday' => $date->dayOfWeek === 5,
                    'events' => $events->filter(function ($event) use ($timezone, $jalaliYear, $jalaliMonth, $day): bool {
                        $local = $event['at']->timezone($timezone);
                        [$jy, $jm, $jd] = JalaliCalendar::fromGregorian((int) $local->format('Y'), (int) $local->format('m'), (int) $local->format('d'));
                        return $jy === $jalaliYear && $jm === $jalaliMonth && $jd === $day;
                    })->values(),
                ];
            });
            $firstGregorian = $start;
            $leading = ($firstGregorian->dayOfWeek + 1) % 7;
            $todayTehran = CarbonImmutable::now($timezone);
            [$todayYear, $todayMonth, $todayDay] = JalaliCalendar::fromGregorian((int) $todayTehran->format('Y'), (int) $todayTehran->format('m'), (int) $todayTehran->format('d'));
            $todayKey = JalaliCalendar::key($todayYear, $todayMonth, $todayDay);
            $days = $days->map(function (array $day) use ($todayKey): array {
                $day['today'] = $day['key'] === $todayKey;
                return $day;
            });
            $weekdays = JalaliCalendar::WEEKDAYS;
        } else {
            $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($month, $events): array {
                $date = $month->setDay($day);
                $dateKey = $date->format('Y-m-d');
                return ['date' => $date, 'key' => $dateKey, 'day_number' => $date->day, 'weekday' => $date->locale('en')->isoFormat('ddd'), 'friday' => false, 'today' => $date->isToday(), 'events' => $events->filter(fn ($event) => $event['at']->timezone($date->timezone)->format('Y-m-d') === $dateKey)->values()];
            });
            $leading = $month->startOfMonth()->dayOfWeekIso - 1;
            $weekdays = [];
        }
        $summary = [
            'tasks' => $events->where('kind', 'task')->count(),
            'home_service' => $events->where('kind', 'home_service')->count(),
            'referral_expiry' => $events->where('kind', 'referral_expiry')->count(),
            'total' => $events->count(),
        ];

        return view('panel.calendar.index', [
            ...WorkspaceView::data($request, 'calendar'),
            'month' => $jalaliMode ? null : $month,
            'monthInput' => $monthInput,
            'monthLabel' => $monthLabel,
            'monthQueryKey' => $jalaliMode ? 'jmonth' : 'month',
            'previousMonth' => $previousMonth,
            'nextMonth' => $nextMonth,
            'jalaliMode' => $jalaliMode,
            'weekdays' => $weekdays,
            'leading' => $leading,
            'days' => $days,
            'events' => $events,
            'summary' => $summary,
            'serverWindow' => $serverWindow,
        ])->with('locale', $locale);
    }
}
