@extends('dashboard.layout')
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['clinics']) }}</div><div class="label">{{ __('ui.dashboard.my_clinics') }}</div></div>
    <div class="stat"><div class="num">{{ count($data['active_referral_grants']) }}</div><div class="label">{{ __('ui.dashboard.active_grants') }}</div></div>
    <div class="stat"><div class="num">{{ $data['pending_proposals'] }}</div><div class="label">{{ __('ui.dashboard.pending_proposals') }}</div></div>
</section>

<div class="card">
    <h2>{{ __('ui.dashboard.my_clinics') }}</h2>
    @if(count($data['clinics']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_clinics') }}</div>
    @else
    <table><thead><tr><th scope="col">{{ __('ui.dashboard.col_name') }}</th><th scope="col">{{ __('ui.dashboard.col_status') }}</th></tr></thead>
        <tbody>@foreach($data['clinics'] as $c)<tr><td>{{ $c['name'] }}</td><td>@if($c['is_active'])<span class="ok">{{ __('ui.dashboard.active') }}</span>@else<span class="muted">{{ __('ui.dashboard.inactive') }}</span>@endif</td></tr>@endforeach</tbody>
    </table>
    @endif
</div>

<div class="card">
    <h2>{{ __('ui.dashboard.active_grants') }}</h2>
    @if(count($data['active_referral_grants']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_grants') }}</div>
    @else
    <table><thead><tr><th scope="col">{{ __('ui.dashboard.col_case') }}</th><th scope="col">{{ __('ui.dashboard.col_status') }}</th><th scope="col">{{ __('ui.dashboard.col_granted') }}</th><th scope="col">{{ __('ui.dashboard.col_expires') }}</th></tr></thead>
        <tbody>@foreach($data['active_referral_grants'] as $g)<tr><td>{{ $g['case_id'] }}</td><td><span class="badge {{ $g['case_status'] }}">{{ __('ui.dashboard.status.'.$g['case_status']) }}</span></td><td>{{ $g['granted_at'] }}</td><td>{{ $g['expires_at'] }}</td></tr>@endforeach</tbody>
    </table>
    @endif
</div>
@endsection
