@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['case_queue']) }}</div><div class="label">{{ __('ui.dashboard.case_queue') }}</div></div>
    <div class="stat"><div class="num">{{ $data['awaiting_patient_count'] }}</div><div class="label">{{ __('ui.dashboard.awaiting_patient') }}</div></div>
    <div class="stat"><div class="num">{{ $data['open_unassigned_support'] }}</div><div class="label">{{ __('ui.dashboard.open_support') }}</div></div>
    <div class="stat"><div class="num">{{ $data['home_service_pending'] }}</div><div class="label">{{ __('ui.dashboard.home_pending') }}</div></div>
    <div class="stat"><div class="num">{{ $data['task_summary']['open'] + $data['task_summary']['in_progress'] }}</div><div class="label">{{ __('panel.tasks.title') }}</div></div>
    <div class="stat"><div class="num">{{ $data['task_summary']['overdue'] }}</div><div class="label">{{ __('panel.tasks.overdue') }}</div></div>
</section>
<section class="quick-action-grid" aria-label="{{ __('ui.dashboard.next_actions') }}">
    <a class="quick-action primary" href="{{ route('panel.tasks.index', ['locale'=>$locale]) }}"><strong>{{ __('panel.tasks.title') }}</strong><small>{{ __('panel.tasks.due_today') }}: {{ $data['task_summary']['due_today'] }}</small></a>
    <a class="quick-action" href="{{ route('panel.support.index', ['locale'=>$locale]) }}"><strong>{{ __('panel.nav.support') }}</strong><small>{{ $data['open_unassigned_support'] }} {{ __('ui.dashboard.open_support') }}</small></a>
    <a class="quick-action" href="{{ route('panel.home-service.index', ['locale'=>$locale]) }}"><strong>{{ __('panel.nav.home_service') }}</strong><small>{{ $data['home_service_pending'] }} {{ __('ui.dashboard.home_pending') }}</small></a>
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
    <div class="card-head"><span>{{ __('panel.tasks.title') }}</span><a class="btn sm" href="{{ route('panel.tasks.index', ['locale'=>$locale]) }}">{{ __('panel.tasks.all') }}</a></div>
    @forelse($data['active_tasks'] as $task)
        <article class="task-card {{ $task['overdue'] ? 'overdue-card' : '' }}">
            <div class="task-card-main">
                <a class="case-link" href="{{ route('panel.case', ['locale'=>$locale, 'case'=>$task['case_id']]) }}"><bdi>{{ $task['public_reference'] }}</bdi></a>
                <div class="task-meta">
                    <span class="badge">{{ __('panel.tasks.types.'.$task['task_type']) }}</span>
                    <span class="badge {{ $task['status'] }}">{{ __('panel.tasks.status.'.$task['status']) }}</span>
                    @if($task['due_at']) · <bdi>{{ $task['due_at']->timezone(config('royadarman.display_timezone'))->format('Y-m-d H:i') }}</bdi>@endif
                </div>
            </div>
        </article>
    @empty
        <div class="empty">{{ __('panel.tasks.empty') }}</div>
    @endforelse
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
