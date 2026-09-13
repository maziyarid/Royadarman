@extends('dashboard.layout')
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['cases']) }}</div><div class="label">{{ __('ui.dashboard.my_cases') }}</div></div>
    <div class="stat"><div class="num">{{ $data['open_referral_proposals'] }}</div><div class="label">{{ __('ui.dashboard.open_referrals') }}</div></div>
    <div class="stat"><div class="num">{{ count($data['support_conversations']) }}</div><div class="label">{{ __('ui.dashboard.support') }}</div></div>
</section>

<div class="card">
    <h2>{{ __('ui.dashboard.my_cases') }}</h2>
    @forelse($data['cases'] as $case)
        <table>
            <tr><th scope="row">{{ __('ui.dashboard.col_id') }}</th><td>{{ $case['id'] }}</td></tr>
            <tr><th scope="row">{{ __('ui.dashboard.col_status') }}</th><td><span class="badge {{ $case['status'] }}">{{ __('ui.dashboard.status.'.$case['status']) }}</span></td></tr>
            <tr><th scope="row">{{ __('ui.dashboard.col_service') }}</th><td>{{ __('ui.dashboard.service.'.$case['service_type']) }}</td></tr>
            <tr><th scope="row">{{ __('ui.dashboard.col_documents') }}</th><td>{{ $case['documents_count'] }}</td></tr>
            <tr><th scope="row">{{ __('ui.dashboard.col_review') }}</th><td>@if($case['has_published_review'])<span class="ok">{{ __('ui.dashboard.yes') }}</span>@else<span class="muted">{{ __('ui.dashboard.no') }}</span>@endif</td></tr>
            <tr><th scope="row">{{ __('ui.dashboard.col_updated') }}</th><td>{{ $case['created_at'] }}</td></tr>
        </table>
    @empty
        <div class="empty">{{ __('ui.dashboard.no_cases') }}</div>
    @endforelse
</div>

<div class="card">
    <h2>{{ __('ui.dashboard.home_service_requests') }}</h2>
    @forelse($data['home_service_requests'] as $h)
        <table><tr><th scope="row">{{ __('ui.dashboard.col_status') }}</th><td><span class="badge {{ $h['status'] }}">{{ __('ui.dashboard.home_status.'.$h['status']) }}</span></td></tr><tr><th scope="row">{{ __('ui.dashboard.col_area') }}</th><td>{{ $h['tehran_area'] }}</td></tr></table>
    @empty
        <div class="empty">{{ __('ui.dashboard.no_home_service') }}</div>
    @endforelse
</div>
@endsection
