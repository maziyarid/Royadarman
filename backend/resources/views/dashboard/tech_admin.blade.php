@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ $data['outbox']['pending'] }}</div><div class="label">{{ __('ui.dashboard.outbox_pending') }}</div></div>
    <div class="stat"><div class="num">{{ $data['outbox']['failed'] }}</div><div class="label">{{ __('ui.dashboard.outbox_failed') }}</div></div>
    <div class="stat"><div class="num">{{ $data['queued_jobs'] }}</div><div class="label">{{ __('panel.metrics.queued_jobs') }}</div></div>
    <div class="stat"><div class="num">{{ $data['failed_jobs'] }}</div><div class="label">{{ __('panel.metrics.failed_jobs') }}</div></div>
    <div class="stat"><div class="num">{{ $data['audit_events_24h'] }}</div><div class="label">{{ __('ui.dashboard.audit_24h') }}</div></div>
    <div class="stat"><div class="num">{{ $data['consent_records'] }}</div><div class="label">{{ __('ui.dashboard.consent_records') }}</div></div>
</section>
<section class="card pad">
    <h2>{{ __('ui.dashboard.readiness') }}</h2>
    <div class="facts">
        <div class="fact"><small>{{ __('ui.dashboard.intake') }}</small>{{ $data['intake_enabled'] ? __('ui.dashboard.on') : __('ui.dashboard.off') }}</div>
        <div class="fact"><small>{{ __('ui.dashboard.demo_flag') }}</small>{{ $data['panel_demo_access'] ? __('ui.dashboard.on') : __('ui.dashboard.off') }}</div>
        <div class="fact"><small>{{ __('ui.dashboard.scanner') }}</small>{{ $data['scanner_enabled'] ? __('ui.dashboard.on') : __('ui.dashboard.off') }}</div>
        <div class="fact"><small>{{ __('ui.dashboard.sms_named') }}</small>{{ $data['sms_provider_configured'] ? __('ui.dashboard.yes') : __('ui.dashboard.no') }}</div>
        <div class="fact"><small>{{ __('ui.dashboard.retention') }}</small>{{ $data['retention_configured'] ? __('ui.dashboard.yes') : __('ui.dashboard.no') }}</div>
    </div>
    <p class="hint">{{ __('ui.dashboard.readiness_note') }}</p>
</section>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.recent_audit') }}</div>
    @if(count($data['recent_audit']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_audit') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('ui.dashboard.col_action') }}</th><th scope="col">{{ __('ui.dashboard.col_updated') }}</th></tr></thead>
                <tbody>
                    @foreach($data['recent_audit'] as $a)
                        <tr>
                            <td>{{ $a['action'] }}</td>
                            <td><bdi>{{ $a['created_at'] }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<p class="notice">{{ __('panel.roles.tech_admin.subtitle') }}</p>
@endsection
