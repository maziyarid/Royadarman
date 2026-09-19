@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['clinics']) }}</div><div class="label">{{ __('ui.dashboard.my_clinics') }}</div></div>
    <div class="stat"><div class="num">{{ count($data['active_referral_grants']) }}</div><div class="label">{{ __('ui.dashboard.active_grants') }}</div></div>
    <div class="stat"><div class="num">{{ $data['pending_proposals'] }}</div><div class="label">{{ __('ui.dashboard.pending_proposals') }}</div></div>
</section>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.my_clinics') }}</div>
    @if(count($data['clinics']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_clinics') }}</div>
    @else
        @foreach($data['clinics'] as $c)
            <article class="task-card">
                <div class="task-card-main">
                    <strong>{{ $c['name'] }}</strong>
                    @if($c['is_active'])<span class="ok">{{ __('ui.dashboard.active') }}</span>@else<span class="muted">{{ __('ui.dashboard.inactive') }}</span>@endif
                </div>
            </article>
        @endforeach
    @endif
</div>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.active_grants') }}</div>
    @if(count($data['active_referral_grants']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_grants') }}</div>
    @else
        @foreach($data['active_referral_grants'] as $g)
            <article class="task-card">
                <div class="task-card-main">
                    @if(!empty($g['public_reference']))
                        <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $g['case_id']]) }}"><bdi>{{ $g['public_reference'] }}</bdi></a>
                    @else
                        <strong>{{ $g['case_id'] }}</strong>
                    @endif
                    <div class="task-meta">
                        <span class="badge {{ $g['case_status'] }}">{{ __('ui.dashboard.status.'.$g['case_status']) }}</span>
                        {{ __('ui.dashboard.col_expires') }}: <bdi>{{ $g['expires_at'] }}</bdi>
                    </div>
                </div>
                @if(($g['expires_in_minutes'] ?? null) !== null)
                    <span class="sla-chip sla-{{ $g['expiry_band'] ?? 'unknown' }}">{{ __('ui.dashboard.expires_in') }} · {{ __('ui.dashboard.wait_minutes', ['minutes' => max(0, $g['expires_in_minutes'])]) }}</span>
                @endif
                @if(!empty($g['case_id']))
                    <a class="btn sm" href="{{ route('panel.case', ['locale' => $locale, 'case' => $g['case_id']]) }}">{{ __('ui.dashboard.next_grants') }}</a>
                @endif
            </article>
        @endforeach
    @endif
</div>
<p class="hint">{{ __('ui.dashboard.wait_note') }}</p>
@endsection
