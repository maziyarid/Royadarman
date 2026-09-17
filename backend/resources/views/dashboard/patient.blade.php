@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="task-grid next-actions" aria-label="{{ __('ui.dashboard.next_actions') }}">
    <a class="task-card action" href="{{ route('patient.request.create', ['locale' => $locale]) }}">
        <strong>{{ __('ui.dashboard.next_new_request') }}</strong>
        <small>{{ __('request.intro') }}</small>
    </a>
    <a class="task-card action" href="{{ route('panel.support.index', ['locale' => $locale]) }}">
        <strong>{{ __('ui.dashboard.next_support') }}</strong>
        <small>{{ __('ui.dashboard.support') }}</small>
    </a>
</section>
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['cases']) }}</div><div class="label">{{ __('ui.dashboard.my_cases') }}</div></div>
    <div class="stat"><div class="num">{{ $data['open_referral_proposals'] }}</div><div class="label">{{ __('ui.dashboard.open_referrals') }}</div></div>
    <div class="stat"><div class="num">{{ count($data['support_conversations']) }}</div><div class="label">{{ __('ui.dashboard.support') }}</div></div>
</section>
<div class="card">
    <h2 class="card-head">{{ __('ui.dashboard.my_cases') }}</h2>
    @forelse($data['cases'] as $case)
        <article class="task-card">
            <div class="task-card-main">
                <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $case['id']]) }}"><bdi>{{ $case['public_reference'] }}</bdi></a>
                <div class="task-meta">
                    <span class="badge {{ $case['status'] }}">{{ __('ui.dashboard.status.'.$case['status']) }}</span>
                    {{ __('ui.dashboard.service.'.$case['service_type']) }}
                </div>
            </div>
            @include('dashboard.partials.wait-chip', ['minutes' => $case['wait_minutes'] ?? null, 'band' => $case['sla_band'] ?? 'unknown'])
            <a class="btn sm" href="{{ route('panel.case', ['locale' => $locale, 'case' => $case['id']]) }}">{{ __('ui.dashboard.task_open') }}</a>
        </article>
    @empty
        <div class="empty">{{ __('ui.dashboard.no_cases') }}</div>
    @endforelse
</div>
<div class="card">
    <h2 class="card-head">{{ __('ui.dashboard.home_service_requests') }}</h2>
    @forelse($data['home_service_requests'] as $h)
        <article class="task-card">
            <div class="task-card-main">
                <a class="case-link" href="{{ route('panel.home-service.show', ['locale' => $locale, 'homeService' => $h['id']]) }}">{{ $h['tehran_area'] }}</a>
                <span class="badge {{ $h['status'] }}">{{ __('ui.dashboard.home_status.'.$h['status']) }}</span>
            </div>
        </article>
    @empty
        <div class="empty">{{ __('ui.dashboard.no_home_service') }}</div>
    @endforelse
</div>
<div class="card">
    <h2 class="card-head">{{ __('ui.dashboard.support') }}</h2>
    @forelse($data['support_conversations'] as $s)
        <article class="task-card">
            <div class="task-card-main">
                <a class="case-link" href="{{ route('panel.support.show', ['locale' => $locale, 'conversation' => $s['id']]) }}">{{ $s['subject'] ?: __('panel.support.untitled') }}</a>
                <span class="badge {{ $s['status'] }}">{{ __('panel.support.status.'.$s['status']) }}</span>
            </div>
        </article>
    @empty
        <div class="empty">{{ __('panel.support.empty') }}</div>
    @endforelse
</div>
<p class="hint">{{ __('ui.dashboard.wait_note') }}</p>
@endsection
