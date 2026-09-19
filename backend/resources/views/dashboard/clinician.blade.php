@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ $data['open_drafts_count'] }}</div><div class="label">{{ __('ui.dashboard.open_drafts') }}</div></div>
    <div class="stat"><div class="num">{{ count($data['assigned_reviews']) }}</div><div class="label">{{ __('ui.dashboard.assigned_reviews') }}</div></div>
</section>
<div class="card pad">
    <h2>{{ __('ui.dashboard.credential') }}</h2>
    <p>
        <span class="badge {{ $data['credential_status'] }}">{{ __('ui.dashboard.credential_status.'.($data['credential_status'] ?: 'unverified')) }}</span>
        —
        @if($data['can_publish'])<span class="ok">{{ __('ui.dashboard.can_publish') }}</span>@else<span class="muted">{{ __('ui.dashboard.cannot_publish') }}</span>@endif
    </p>
</div>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.assigned_reviews') }}</div>
    @if(count($data['assigned_reviews']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_reviews') }}</div>
    @else
        @foreach($data['assigned_reviews'] as $r)
            <article class="task-card">
                <div class="task-card-main">
                    @if(!empty($r['public_reference']))
                        <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $r['case_id']]) }}"><bdi>{{ $r['public_reference'] }}</bdi></a>
                    @else
                        <strong>{{ $r['case_id'] }}</strong>
                    @endif
                    <div class="task-meta">
                        <span class="badge {{ $r['case_status'] ?? '' }}">{{ __('ui.dashboard.status.'.$r['case_status']) }}</span>
                        {{ __('ui.dashboard.col_published') }}:
                        @if($r['is_published'])<span class="ok">{{ __('ui.dashboard.yes') }}</span>@else<span class="muted">{{ __('ui.dashboard.no') }}</span>@endif
                    </div>
                </div>
                @include('dashboard.partials.wait-chip', ['minutes' => $r['wait_minutes'] ?? null, 'band' => $r['sla_band'] ?? 'unknown'])
                @if(!empty($r['case_id']))
                    <a class="btn sm" href="{{ route('panel.case', ['locale' => $locale, 'case' => $r['case_id']]) }}">{{ __('ui.dashboard.next_reviews') }}</a>
                @endif
            </article>
        @endforeach
    @endif
</div>
<p class="hint">{{ __('ui.dashboard.wait_note') }}</p>
@endsection
