@extends('panel.layout')
@section('title', __('panel.analytics.title'))
@section('heading', __('panel.analytics.title'))

@section('content')
<section class="hero-panel">
    <h1>{{ __('panel.analytics.title') }}</h1>
    <p>{{ __('panel.analytics.intro') }}</p>
</section>

<nav class="range-switcher" aria-label="{{ __('panel.analytics.range') }}">
    @foreach(['30d','90d','1y'] as $value)
        <a class="btn {{ $range === $value ? 'primary' : '' }}" href="{{ route('panel.analytics.index', ['locale'=>$locale, 'range'=>$value]) }}">
            {{ __('panel.analytics.ranges.'.$value) }}
        </a>
    @endforeach
</nav>

<section class="stat-grid">
    <article class="stat"><div class="num">{{ $summary['cases'] }}</div><div class="label">{{ __('panel.analytics.cases') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['support_open'] }}</div><div class="label">{{ __('panel.analytics.open_support') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['referrals'] }}</div><div class="label">{{ __('panel.analytics.referrals') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['home_requests'] }}</div><div class="label">{{ __('panel.analytics.home_requests') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['tasks_open'] }}</div><div class="label">{{ __('panel.analytics.open_tasks') }}</div></article>
    <article class="stat">
        <div class="num">{{ $summary['avg_first_response_minutes'] ?? '—' }}</div>
        <div class="label">{{ __('panel.analytics.avg_first_response') }}</div>
    </article>
</section>

<div class="analytics-grid">
    @php
        $groups = [
            ['title' => __('panel.analytics.case_statuses'), 'rows' => $caseStatuses, 'prefix' => 'ui.dashboard.status.'],
            ['title' => __('panel.analytics.service_mix'), 'rows' => $serviceMix, 'prefix' => 'ui.dashboard.service.'],
            ['title' => __('panel.analytics.support_statuses'), 'rows' => $supportStatuses, 'prefix' => 'panel.support.status.'],
            ['title' => __('panel.analytics.referral_statuses'), 'rows' => $referralStatuses, 'prefix' => 'panel.analytics.referral_status.'],
            ['title' => __('panel.analytics.home_statuses'), 'rows' => $homeStatuses, 'prefix' => 'ui.dashboard.home_status.'],
            ['title' => __('panel.analytics.task_statuses'), 'rows' => $taskStatuses, 'prefix' => 'panel.tasks.status.'],
        ];
    @endphp

    @foreach($groups as $group)
        <section class="card pad analytics-card">
            <h2>{{ $group['title'] }}</h2>
            @php $total = max(1, (int) $group['rows']->sum()); @endphp
            @forelse($group['rows'] as $key => $count)
                @php
                    $translationKey = $group['prefix'].$key;
                    $label = __($translationKey);
                    if ($label === $translationKey) $label = str_replace('_', ' ', $key);
                    $pct = min(100, max(0, round(($count / $total) * 100)));
                @endphp
                <div class="metric-bar-row">
                    <div class="metric-bar-label"><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
                    <div class="metric-bar"><i style="width:{{ $pct }}%"></i></div>
                </div>
            @empty
                <div class="empty">{{ __('panel.analytics.empty') }}</div>
            @endforelse
        </section>
    @endforeach
</div>

<div class="analytics-grid">
    <section class="card pad analytics-card">
        <h2>{{ __('panel.analytics.case_trend') }}</h2>
        @php $maxCase = max(1, (int) $caseTrend->max('count')); @endphp
        @forelse($caseTrend as $point)
            <div class="trend-row">
                <span dir="ltr">{{ $point['label'] }}</span>
                <div class="metric-bar"><i style="width:{{ round(($point['count']/$maxCase)*100) }}%"></i></div>
                <strong>{{ $point['count'] }}</strong>
            </div>
        @empty
            <div class="empty">{{ __('panel.analytics.empty') }}</div>
        @endforelse
    </section>

    <section class="card pad analytics-card">
        <h2>{{ __('panel.analytics.support_trend') }}</h2>
        @php $maxSupport = max(1, (int) $supportTrend->max('count')); @endphp
        @forelse($supportTrend as $point)
            <div class="trend-row">
                <span dir="ltr">{{ $point['label'] }}</span>
                <div class="metric-bar"><i style="width:{{ round(($point['count']/$maxSupport)*100) }}%"></i></div>
                <strong>{{ $point['count'] }}</strong>
            </div>
        @empty
            <div class="empty">{{ __('panel.analytics.empty') }}</div>
        @endforelse
    </section>
</div>

<section class="card pad">
    <h2>{{ __('panel.analytics.operational_health') }}</h2>
    <div class="facts">
        <div class="fact"><small>{{ __('panel.analytics.referrals_accepted') }}</small>{{ $summary['referrals_accepted'] }}</div>
        <div class="fact"><small>{{ __('panel.analytics.home_completed') }}</small>{{ $summary['home_completed'] }}</div>
        <div class="fact"><small>{{ __('panel.analytics.tasks_overdue') }}</small>{{ $summary['tasks_overdue'] }}</div>
        <div class="fact"><small>{{ __('panel.analytics.support_opened') }}</small>{{ $summary['support_opened'] }}</div>
    </div>
    <p class="hint">{{ __('panel.analytics.privacy_note') }}</p>
</section>
@endsection
