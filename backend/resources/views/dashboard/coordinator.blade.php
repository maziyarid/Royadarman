@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['case_queue']) }}</div><div class="label">{{ __('ui.dashboard.case_queue') }}</div></div>
    <div class="stat"><div class="num">{{ $data['awaiting_patient_count'] }}</div><div class="label">{{ __('ui.dashboard.awaiting_patient') }}</div></div>
    <div class="stat"><div class="num">{{ $data['open_unassigned_support'] }}</div><div class="label">{{ __('ui.dashboard.open_support') }}</div></div>
    <div class="stat"><div class="num">{{ $data['home_service_pending'] }}</div><div class="label">{{ __('ui.dashboard.home_pending') }}</div></div>
</section>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.case_queue') }}</div>
    @if(count($data['case_queue']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_cases_queue') }}</div>
    @else
        @foreach($data['case_queue'] as $c)
            <article class="task-card">
                <div class="task-card-main">
                    <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $c['id']]) }}"><bdi>{{ $c['public_reference'] }}</bdi></a>
                    <div class="task-meta">
                        <span class="badge {{ $c['status'] }}">{{ __('ui.dashboard.status.'.$c['status']) }}</span>
                        {{ __('ui.dashboard.service.'.$c['service_type']) }}
                        · {{ __('ui.dashboard.col_documents') }} {{ $c['documents_count'] }}
                        · {{ __('ui.dashboard.col_review') }}
                        @if($c['has_published_review'])<span class="ok">{{ __('ui.dashboard.yes') }}</span>@else<span class="muted">{{ __('ui.dashboard.no') }}</span>@endif
                    </div>
                </div>
                @include('dashboard.partials.wait-chip', ['minutes' => $c['wait_minutes'] ?? null, 'band' => $c['sla_band'] ?? 'unknown'])
                <a class="btn sm" href="{{ route('panel.case', ['locale' => $locale, 'case' => $c['id']]) }}">{{ __('ui.dashboard.next_queue') }}</a>
            </article>
        @endforeach
    @endif
</div>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.referral_sla') }}</div>
    @if(count($data['referral_sla'] ?? []) === 0)
        <div class="empty">{{ __('ui.dashboard.no_referral_sla') }}</div>
    @else
        @foreach($data['referral_sla'] as $r)
            <article class="task-card">
                <div class="task-card-main">
                    <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $r['case_id']]) }}"><bdi>{{ $r['public_reference'] }}</bdi></a>
                    <div class="task-meta">
                        @if(($r['expiry_state'] ?? null) === 'silent_loss')
                            <span class="badge overdue">{{ __('ui.dashboard.silent_loss') }}</span>
                        @elseif(($r['expiry_state'] ?? null) === 'expired')
                            <span class="badge overdue">{{ __('ui.dashboard.referral_expired') }}</span>
                        @else
                            <span class="badge">{{ __('ui.dashboard.open_referrals') }}</span>
                        @endif
                    </div>
                </div>
                @include('dashboard.partials.wait-chip', ['minutes' => $r['wait_minutes'] ?? null, 'band' => $r['sla_band'] ?? 'unknown'])
                <a class="btn sm" href="{{ route('panel.case', ['locale' => $locale, 'case' => $r['case_id']]) }}">{{ __('ui.dashboard.task_open') }}</a>
            </article>
        @endforeach
    @endif
</div>
<p class="hint">{{ __('ui.dashboard.wait_note') }}</p>
<p class="hint">{{ __('ui.dashboard.referral_wait_note') }}</p>
@endsection
