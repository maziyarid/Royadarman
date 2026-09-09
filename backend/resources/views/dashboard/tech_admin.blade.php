@extends('dashboard.layout')
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ $data['outbox']['pending'] }}</div><div class="label">{{ __('ui.dashboard.outbox_pending') }}</div></div>
    <div class="stat"><div class="num">{{ $data['outbox']['failed'] }}</div><div class="label">{{ __('ui.dashboard.outbox_failed') }}</div></div>
    <div class="stat"><div class="num">{{ $data['audit_events_24h'] }}</div><div class="label">{{ __('ui.dashboard.audit_24h') }}</div></div>
    <div class="stat"><div class="num">{{ $data['consent_records'] }}</div><div class="label">{{ __('ui.dashboard.consent_records') }}</div></div>
</section>

<div class="card">
    <h2>{{ __('ui.dashboard.recent_audit') }}</h2>
    @if(count($data['recent_audit']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_audit') }}</div>
    @else
    <table><thead><tr><th>{{ __('ui.dashboard.col_id') }}</th><th>{{ __('ui.dashboard.col_action') }}</th><th>{{ __('ui.dashboard.col_actor') }}</th><th>{{ __('ui.dashboard.col_updated') }}</th></tr></thead>
        <tbody>@foreach($data['recent_audit'] as $a)<tr><td>{{ $a['id'] }}</td><td>{{ $a['action'] }}</td><td>{{ $a['actor_user_id'] }}</td><td>{{ $a['created_at'] }}</td></tr>@endforeach</tbody>
    </table>
    @endif
</div>
@endsection
