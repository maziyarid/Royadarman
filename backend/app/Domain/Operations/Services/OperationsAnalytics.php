<?php

namespace App\Domain\Operations\Services;

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\HomeServiceStatus;
use App\Domain\Cases\Enums\ServiceType;
use App\Domain\Support\Enums\ConversationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Count-only current-state cohorts; no historical status reconstruction or clinical reads.
 * Reads share one transaction; cross-query MVCC consistency requires the connection's
 * repeatable-read isolation (observed on production). No historical state is reconstructed.
 */
final class OperationsAnalytics
{
    private const TIMEZONE = 'Asia/Tehran';

    public function report(mixed $requestedRange): array
    {
        return DB::transaction(fn () => $this->buildReport($requestedRange));
    }

    private function buildReport(mixed $requestedRange): array
    {
        $range = is_string($requestedRange) && in_array($requestedRange, ['30d', '90d', '1y'], true) ? $requestedRange : '30d';
        $days = match ($range) {
            '90d' => 90, '1y' => 365, default => 30
        };
        // Stored timestamps have second precision. Rolling local days retain local
        // wall time across historical Tehran DST changes; queries use UTC instants.
        $until = CarbonImmutable::now('UTC')->startOfSecond();
        $since = $until->setTimezone(self::TIMEZONE)->subDays($days)->utc();
        $cases = $this->cohort('patient_cases', 'created_at', $since, $until);
        $support = $this->cohort('support_conversations', 'opened_at', $since, $until);
        $referrals = $this->cohort('referral_proposals', 'proposed_at', $since, $until);
        $home = $this->cohort('home_service_requests', 'created_at', $since, $until);
        $tasks = $this->cohort('coordination_tasks', 'created_at', $since, $until);
        $openSupport = array_map(fn (ConversationStatus $status) => $status->value, array_filter(ConversationStatus::cases(), fn ($status) => $status->isOpen()));
        $caseStatuses = $this->countBy($cases, 'status', array_column(CaseStatus::cases(), 'value'));
        $supportStatuses = $this->countBy($support, 'status', array_column(ConversationStatus::cases(), 'value'));
        $referralStatuses = $this->referralCounts($referrals, $until);
        $homeStatuses = $this->countBy($home, 'status', array_column(HomeServiceStatus::cases(), 'value'));
        $taskStatuses = $this->countBy($tasks, 'status', ['open', 'in_progress', 'done']);

        return [
            'range' => $range,
            'days' => $days,
            'reportWindow' => ['since' => $since->toIso8601String(), 'until' => $until->toIso8601String(), 'timezone' => self::TIMEZONE, 'week_starts_on' => 6],
            'summary' => [
                'cases' => (int) $caseStatuses->sum(),
                'support_opened' => (int) $supportStatuses->sum(),
                'support_open' => (int) $supportStatuses->only($openSupport)->sum(),
                'avg_first_response_minutes' => $this->averageFirstResponse($support, $until),
                'referrals' => (int) $referralStatuses->sum(),
                'referrals_accepted' => $referralStatuses->get('accepted', 0),
                'home_requests' => (int) $homeStatuses->sum(),
                'home_completed' => $homeStatuses->get(HomeServiceStatus::Completed->value, 0),
                'tasks_open' => (int) $taskStatuses->only(['open', 'in_progress'])->sum(),
                'tasks_overdue' => (clone $tasks)->whereIn('status', ['open', 'in_progress'])->where('due_at', '<', $until)->count(),
            ],
            'caseStatuses' => $caseStatuses,
            'serviceMix' => $this->countBy($cases, 'service_type', array_column(ServiceType::cases(), 'value')),
            'supportStatuses' => $supportStatuses,
            'referralStatuses' => $referralStatuses,
            'homeStatuses' => $homeStatuses,
            'taskStatuses' => $taskStatuses,
            'caseTrend' => $this->weeklyTrend($cases, 'created_at', $since, $until),
            'supportTrend' => $this->weeklyTrend($support, 'opened_at', $since, $until),
        ];
    }

    private function cohort(string $table, string $timestamp, CarbonImmutable $since, CarbonImmutable $until): Builder
    {
        return DB::table($table)->where($timestamp, '>=', $since)->where($timestamp, '<', $until);
    }

    /** Bounded SQL buckets; malformed status text never becomes a display label. */
    private function countBy(Builder $query, string $column, array $allowed): Collection
    {
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        return (clone $query)->selectRaw("CASE WHEN {$column} IN ({$placeholders}) THEN {$column} ELSE 'unknown' END AS bucket, COUNT(*) AS total", $allowed)
            ->groupBy('bucket')->orderByDesc('total')->orderBy('bucket')->get()
            ->mapWithKeys(fn ($row) => [$row->bucket => (int) $row->total]);
    }

    private function averageFirstResponse(Builder $query, CarbonImmutable $until): ?int
    {
        $seconds = 0.0;
        $count = 0;
        $responses = (clone $query)->whereNotNull('first_response_at')
            ->whereColumn('first_response_at', '>=', 'opened_at')->where('first_response_at', '<', $until)
            ->select(['id', 'opened_at', 'first_response_at'])->lazyById(500);
        foreach ($responses as $row) {
            $seconds += CarbonImmutable::parse($row->opened_at, 'UTC')->diffInSeconds(CarbonImmutable::parse($row->first_response_at, 'UTC'));
            $count++;
        }

        return $count === 0 ? null : (int) round($seconds / (60 * $count));
    }

    private function referralCounts(Builder $query, CarbonImmutable $until): Collection
    {
        // Withdrawal does not rewrite status. Expiry/SilentLoss are persisted
        // lifecycle evidence, not an invented proposal deadline or grant expiry.
        $bucket = "CASE WHEN withdrawn_at IS NOT NULL THEN 'withdrawn'
            WHEN status = 'proposed' AND EXISTS (
                SELECT 1 FROM referral_lifecycle_events
                WHERE proposal_id = referral_proposals.id
                    AND case_id = referral_proposals.case_id
                    AND event_type = 'silent_loss' AND created_at < ?
            ) THEN 'silent_loss'
            WHEN status = 'proposed' AND EXISTS (
                SELECT 1 FROM referral_lifecycle_events
                WHERE proposal_id = referral_proposals.id
                    AND case_id = referral_proposals.case_id
                    AND event_type = 'expired' AND created_at < ?
            ) THEN 'expired'
            WHEN status IN ('proposed', 'accepted', 'declined') THEN status
            ELSE 'unknown' END";

        return (clone $query)->selectRaw($bucket.' AS bucket, COUNT(*) AS total', [$until, $until])
            ->groupBy('bucket')->orderByDesc('total')->orderBy('bucket')->get()
            ->mapWithKeys(fn ($row) => [$row->bucket => (int) $row->total]);
    }

    /** Gregorian labels of Tehran Saturdays; edge weeks count ONLY the clipped cohort. */
    private function weeklyTrend(Builder $query, string $field, CarbonImmutable $since, CarbonImmutable $until): Collection
    {
        $bins = [];
        $localUntil = $until->setTimezone(self::TIMEZONE);
        for ($week = $since->setTimezone(self::TIMEZONE)->startOfWeek(6); $week < $localUntil; $week = $week->addWeek()) {
            $bins[$week->format('Y-m-d')] = 0;
        }
        foreach ((clone $query)->select(['id', $field])->lazyById(500) as $row) {
            $label = CarbonImmutable::parse($row->{$field}, 'UTC')->setTimezone(self::TIMEZONE)->startOfWeek(6)->format('Y-m-d');
            $bins[$label]++;
        }

        return collect($bins)->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])->values();
    }
}
