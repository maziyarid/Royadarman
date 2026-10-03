@extends('panel.layout')
@section('title', __('panel.analytics.title'))
@section('heading', __('panel.analytics.title'))
@push('scripts')
<link rel="stylesheet" href="/assets/analytics-workspace.css?v=20261003">
@endpush

@section('content')
@php
    $countValue = static fn ($value) => is_int($value) && $value >= 0 ? $value : null;
    $windowDate = static function ($value) {
        if (!is_string($value) || strlen($value) > 40 || !preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) return null;
        try {
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
            return $date && \DateTimeImmutable::getLastErrors() === false ? $date : null;
        } catch (\Throwable) { return null; }
    };
    $window = $reportWindow ?? [];
    $since = $windowDate($window['since'] ?? null);
    $until = $windowDate($window['until'] ?? null);
    $validWindow = $since && $until && $since < $until && ($window['timezone'] ?? null) === 'Asia/Tehran' && ($window['week_starts_on'] ?? null) === 6;
    $metrics = ['cases', 'support_open', 'referrals', 'home_requests', 'tasks_open', 'avg_first_response_minutes'];
    $groups = [
        ['id' => 'cases', 'title' => __('panel.analytics.case_statuses'), 'rows' => $caseStatuses, 'prefix' => 'ui.dashboard.status.'],
        ['id' => 'services', 'title' => __('panel.analytics.service_mix'), 'rows' => $serviceMix, 'prefix' => 'ui.dashboard.service.'],
        ['id' => 'support', 'title' => __('panel.analytics.support_statuses'), 'rows' => $supportStatuses, 'prefix' => 'panel.support.status.'],
        ['id' => 'referrals', 'title' => __('panel.analytics.referral_statuses'), 'rows' => $referralStatuses, 'prefix' => 'analytics.referral_status.'],
        ['id' => 'home', 'title' => __('panel.analytics.home_statuses'), 'rows' => $homeStatuses, 'prefix' => 'ui.dashboard.home_status.'],
        ['id' => 'tasks', 'title' => __('panel.analytics.task_statuses'), 'rows' => $taskStatuses, 'prefix' => 'panel.tasks.status.'],
    ];
@endphp
<div class="analytics-workspace">
    <section class="analytics-hero" aria-labelledby="analytics-intro">
        <div>
            <p class="analytics-eyebrow">{{ __('analytics.eyebrow') }}</p>
            <h2 id="analytics-intro">{{ __('analytics.overview') }}</h2>
            <p>{{ __('analytics.intro') }}</p>
        </div>
        <nav class="analytics-range" aria-label="{{ __('panel.analytics.range') }}">
            @foreach(['30d', '90d', '1y'] as $value)
                <a href="{{ route('panel.analytics.index', ['locale' => $locale, 'range' => $value]) }}" @if($range === $value) aria-current="page" @endif>{{ __('panel.analytics.ranges.'.$value) }}</a>
            @endforeach
        </nav>
    </section>
    <section class="analytics-window" aria-label="{{ __('panel.analytics.range') }}">
        @if($validWindow)
            <dl>
                @foreach(['range_start' => $since, 'range_end' => $until] as $label => $date)
                    <div><dt>{{ __('analytics.'.$label) }}</dt><dd><time dir="ltr" datetime="{{ $date->format(\DateTimeInterface::ATOM) }}">{{ $date->setTimezone(new \DateTimeZone('Asia/Tehran'))->format('Y-m-d H:i') }}</time></dd></div>
                @endforeach
            </dl>
            <p>{{ __('analytics.timezone_help') }}</p>
        @else
            <p>{{ __('analytics.window_unknown') }}</p>
        @endif
        <p>{{ __('analytics.cohort_help') }}</p>
    </section>
    <nav class="analytics-sections" aria-label="{{ __('analytics.sections') }}">
        @foreach(['overview' => 'overview_section', 'distributions' => 'distributions', 'trends' => 'trends', 'outcomes' => 'outcomes'] as $id => $label)
            <a href="#analytics-{{ $id }}">{{ __('analytics.'.$label) }}</a>
        @endforeach
    </nav>
    <section id="analytics-overview" aria-labelledby="analytics-overview-title">
        <h2 id="analytics-overview-title" class="analytics-section-heading">{{ __('analytics.overview_section') }}</h2>
        <div class="analytics-metrics">
            @foreach($metrics as $metric)
                @php $value = $countValue($summary[$metric] ?? null); @endphp
                <article class="analytics-metric">
                    <h3>{{ __('analytics.'.$metric) }}</h3>
                    <p class="analytics-value" data-metric="{{ $metric }}">{{ $value ?? ($metric === 'avg_first_response_minutes' ? __('analytics.no_response') : __('analytics.unavailable')) }}</p>
                </article>
            @endforeach
        </div>
        <p class="analytics-note">{{ __('analytics.response_help') }}</p>
    </section>
    <section id="analytics-distributions" aria-labelledby="analytics-distributions-title">
        <h2 id="analytics-distributions-title" class="analytics-section-heading">{{ __('analytics.distributions') }}</h2>
        <p class="analytics-note">{{ __('analytics.status_help') }}</p>
        <p class="analytics-note">{{ __('analytics.distribution_help') }}</p>
        <div class="analytics-panels">
            @foreach($groups as $group)
                @php
                    $validCounts = $group['rows']->filter(fn ($value) => $countValue($value) !== null);
                    $total = $validCounts->count() === $group['rows']->count() ? $validCounts->sum() : null;
                @endphp
                <section class="analytics-panel" aria-labelledby="analytics-group-{{ $group['id'] }}">
                    <header><h3 id="analytics-group-{{ $group['id'] }}">{{ $group['title'] }}</h3><p>{{ $total === null ? __('analytics.unavailable') : __('analytics.group_total', ['count' => $total]) }}</p></header>
                    @if($group['id'] === 'referrals')
                        <p class="analytics-note">{{ __('analytics.referral_help') }}</p>
                    @endif
                    <ul class="analytics-bars">
                        @forelse($group['rows'] as $key => $count)
                            @php
                                $translationKey = $group['prefix'].$key;
                                $label = __($translationKey);
                                if ($label === $translationKey) $label = __('analytics.unknown_category');
                                $value = $countValue($count);
                            @endphp
                            <li>
                                <div class="analytics-bar-heading"><span>{{ $label }}</span><strong>{{ $value ?? __('analytics.unavailable') }}</strong></div>
                                @if($value !== null && $total !== null)
                                    <progress max="{{ max(1, $total) }}" value="{{ $value }}" aria-label="{{ __('analytics.bar_label', ['label' => $label, 'count' => $value, 'total' => $total]) }}">{{ $value }} / {{ $total }}</progress>
                                @endif
                            </li>
                        @empty
                            <li class="analytics-empty">{{ __('analytics.empty') }}</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </section>
    <section id="analytics-trends" aria-labelledby="analytics-trends-title">
        <h2 id="analytics-trends-title" class="analytics-section-heading">{{ __('analytics.trends') }}</h2>
        <p class="analytics-note">{{ __('analytics.week_help') }}</p>
        <div class="analytics-panels analytics-trends">
            @foreach(['case' => $caseTrend, 'support' => $supportTrend] as $kind => $points)
                @php
                    $validCounts = $points->pluck('count')->filter(fn ($value) => $countValue($value) !== null);
                    $maximum = max(1, $validCounts->max() ?? 0);
                    $allZero = $points->isNotEmpty() && $validCounts->count() === $points->count() && $validCounts->sum() === 0;
                @endphp
                <section class="analytics-panel" aria-labelledby="analytics-trend-{{ $kind }}">
                    <header><h3 id="analytics-trend-{{ $kind }}">{{ __('panel.analytics.'.$kind.'_trend') }}</h3></header>
                    @if($allZero)<p class="analytics-empty">{{ __('analytics.all_zero') }}</p>@endif
                    <ul class="analytics-bars">
                        @forelse($points as $point)
                            @php
                                $value = $countValue($point['count'] ?? null);
                                $week = is_string($point['label'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $point['label']) ? $point['label'] : __('analytics.unavailable');
                            @endphp
                            <li>
                                <div class="analytics-bar-heading"><span dir="ltr">{{ $week }}</span><strong>{{ $value ?? __('analytics.unavailable') }}</strong></div>
                                @if($value !== null)
                                    <progress max="{{ $maximum }}" value="{{ $value }}" aria-label="{{ __('analytics.trend_label', ['week' => $week, 'count' => $value]) }}">{{ $value }}</progress>
                                @endif
                            </li>
                        @empty
                            <li class="analytics-empty">{{ __('analytics.empty') }}</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </section>
    <section id="analytics-outcomes" aria-labelledby="analytics-outcomes-title">
        <h2 id="analytics-outcomes-title" class="analytics-section-heading">{{ __('analytics.outcomes') }}</h2>
        <div class="analytics-metrics analytics-secondary">
            @foreach(['referrals_accepted', 'home_completed', 'tasks_overdue', 'support_opened'] as $metric)
                @php $value = $countValue($summary[$metric] ?? null); @endphp
                <article class="analytics-metric"><h3>{{ __('analytics.'.$metric) }}</h3><p class="analytics-value" data-metric="{{ $metric }}">{{ $value ?? __('analytics.unavailable') }}</p></article>
            @endforeach
        </div>
        <p class="analytics-note analytics-privacy">{{ __('panel.analytics.privacy_note') }}</p>
    </section>
</div>
@endsection
