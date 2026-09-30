<?php

namespace App\Http\Controllers\Web;

use App\Domain\Identity\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\DigitNormalizer;
use App\Support\JalaliCalendar;
use App\Support\WorkspaceView;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class NotificationDeliveryController extends Controller
{
    private const DISPLAY_TIMEZONE = 'Asia/Tehran';

    public function index(Request $request, string $locale): View|StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $user?->is_active
            && in_array($user->role, [UserRole::Owner, UserRole::TechnicalAdministrator], true)
            && ! $request->session()->get('panel_demo', false),
            403,
        );

        foreach (['from', 'to'] as $dateKey) {
            if (is_string($request->input($dateKey))) {
                $request->merge([$dateKey => DigitNormalizer::latin($request->input($dateKey))]);
            }
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['sending', 'sent', 'delivered', 'failed'])],
            'locale' => ['nullable', Rule::in(['fa', 'ar', 'en'])],
            'channel' => ['nullable', Rule::in(['sms'])],
            'from' => ['nullable', 'regex:/^\\d{4}-\\d{2}-\\d{2}$/'],
            'to' => ['nullable', 'regex:/^\\d{4}-\\d{2}-\\d{2}$/'],
            'export' => ['nullable', Rule::in(['csv'])],
        ]);

        $fromUtc = $this->localDateBoundary((string) ($filters['from'] ?? ''), $locale, false);
        $toUtc = $this->localDateBoundary((string) ($filters['to'] ?? ''), $locale, true);
        if (($filters['from'] ?? '') !== '' && ($filters['to'] ?? '') !== '') {
            $fromDate = $this->localCalendarDate((string) $filters['from'], $locale);
            $toDate = $this->localCalendarDate((string) $filters['to'], $locale);
            abort_unless($fromDate <= $toDate && $fromDate->diffInDays($toDate) <= 365, 422, __('panel.deliveries.date_range_error'));
        }

        $query = DB::table('notification_deliveries as d')
            ->leftJoin('outbox_events as o', 'o.id', '=', 'd.outbox_event_id')
            ->select([
                'd.channel', 'd.status', 'd.recipient_locale', 'd.template_key',
                'd.failure_code', 'd.created_at', 'o.event_type', 'o.attempts',
            ]);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('d.template_key', 'like', '%'.$search.'%')
                    ->orWhere('o.event_type', 'like', '%'.$search.'%')
                    ->orWhere('d.failure_code', 'like', '%'.$search.'%');
            });
        }
        if (! empty($filters['status'])) $query->where('d.status', $filters['status']);
        if (! empty($filters['locale'])) $query->where('d.recipient_locale', $filters['locale']);
        if (! empty($filters['channel'])) $query->where('d.channel', $filters['channel']);
        if ($fromUtc !== null) $query->where('d.created_at', '>=', $fromUtc);
        if ($toUtc !== null) $query->where('d.created_at', '<', $toUtc);

        if (($filters['export'] ?? '') === 'csv') {
            $rows = (clone $query)->orderByDesc('d.created_at')->limit(10000)->get();
            $filename = 'royadarman-deliveries-'.$this->nowLabel($locale).'.csv';

            return response()->streamDownload(function () use ($rows, $locale): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\\xEF\\xBB\\xBF");
                fputcsv($out, [
                    __('panel.deliveries.created'), __('panel.deliveries.event'),
                    __('panel.deliveries.template'), __('panel.deliveries.channel'),
                    __('panel.deliveries.locale'), __('panel.table.status'),
                    __('panel.deliveries.attempts'), __('panel.deliveries.failure'),
                ]);
                foreach ($rows as $row) {
                    fputcsv($out, array_map($this->safeCsv(...), [
                        $this->displayDate((string) $row->created_at, $locale),
                        $row->event_type ?? '', $row->template_key ?? '', strtoupper((string) $row->channel),
                        strtoupper((string) $row->recipient_locale),
                        __('panel.deliveries.status.'.$row->status), (string) ((int) ($row->attempts ?? 0)),
                        $row->failure_code ?? '',
                    ]));
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $deliveries = $query->orderByDesc('d.created_at')->paginate(40)->withQueryString();
        $summary = [
            'pending_outbox' => DB::table('outbox_events')->whereNull('processed_at')->count(),
            'sending' => DB::table('notification_deliveries')->where('status', 'sending')->count(),
            'sent' => DB::table('notification_deliveries')->where('status', 'sent')->count(),
            'delivered' => DB::table('notification_deliveries')->where('status', 'delivered')->count(),
            'failed' => DB::table('notification_deliveries')->where('status', 'failed')->count(),
            'stuck' => DB::table('outbox_events')->whereNull('processed_at')->where('available_at', '<=', now()->subMinutes(15))->count(),
            'last_24h' => DB::table('notification_deliveries')->where('created_at', '>=', now()->subDay())->count(),
        ];

        foreach ($deliveries as $row) {
            $row->display_created_at = $this->displayDate((string) $row->created_at, $locale);
        }

        return view('panel.deliveries.index', [
            ...WorkspaceView::data($request, 'deliveries'),
            'deliveries' => $deliveries,
            'summary' => $summary,
            'updatedAt' => $this->displayDate(now('UTC')->format('Y-m-d H:i:s'), $locale),
            'filters' => [
                'q' => $search, 'status' => (string) ($filters['status'] ?? ''),
                'locale' => (string) ($filters['locale'] ?? ''), 'channel' => (string) ($filters['channel'] ?? ''),
                'from' => (string) ($filters['from'] ?? ''), 'to' => (string) ($filters['to'] ?? ''),
            ],
        ])->with('locale', $locale);
    }

    private function localDateBoundary(string $value, string $locale, bool $exclusiveEnd): ?string
    {
        if ($value === '') return null;
        $date = $this->localCalendarDate($value, $locale);
        if ($exclusiveEnd) $date = $date->addDay();

        return $date->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    private function localCalendarDate(string $value, string $locale): CarbonImmutable
    {
        if (! preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $value, $parts)) abort(422, __('panel.deliveries.invalid_date'));
        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];
        if ($locale === 'fa') {
            abort_unless(JalaliCalendar::isValid($year, $month, $day), 422, __('panel.deliveries.invalid_date'));
            [$year, $month, $day] = JalaliCalendar::toGregorian($year, $month, $day);
        } else {
            abort_unless(checkdate($month, $day, $year), 422, __('panel.deliveries.invalid_date'));
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, self::DISPLAY_TIMEZONE);
    }

    private function displayDate(string $utc, string $locale): string
    {
        $date = CarbonImmutable::parse($utc, 'UTC')->setTimezone(self::DISPLAY_TIMEZONE);
        if ($locale === 'fa') {
            [$year, $month, $day] = JalaliCalendar::fromGregorian((int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d'));
            return JalaliCalendar::toPersianDigits(sprintf('%04d/%02d/%02d %s', $year, $month, $day, $date->format('H:i')));
        }

        return $date->format('Y-m-d H:i');
    }

    private function nowLabel(string $locale): string
    {
        return now(self::DISPLAY_TIMEZONE)->format($locale === 'fa' ? 'Y-m-d' : 'Y-m-d');
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[\\s]*[=+@\\-]/u', $value) ? "'".$value : $value;
    }
}
