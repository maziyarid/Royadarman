@extends('dashboard.layout')
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ $data['total_cases'] }}</div><div class="label">{{ __('ui.dashboard.total_cases') }}</div></div>
    <div class="stat"><div class="num">{{ $data['total_clinics'] }}</div><div class="label">{{ __('ui.dashboard.total_clinics') }}</div></div>
    <div class="stat"><div class="num">{{ $data['active_clinics'] }}</div><div class="label">{{ __('ui.dashboard.active_clinics') }}</div></div>
    <div class="stat"><div class="num">{{ $data['verified_practitioners'] }}</div><div class="label">{{ __('ui.dashboard.verified_practitioners') }}</div></div>
    <div class="stat"><div class="num">{{ $data['open_support'] }}</div><div class="label">{{ __('ui.dashboard.open_support') }}</div></div>
    <div class="stat"><div class="num">{{ $data['published_posts'] }}</div><div class="label">{{ __('ui.dashboard.published_posts') }}</div></div>
</section>

<div class="card">
    <h2>{{ __('ui.dashboard.case_status_breakdown') }}</h2>
    @if($data['case_status_counts']->isEmpty())
        <div class="empty">{{ __('ui.dashboard.no_cases') }}</div>
    @else
    <table><thead><tr><th scope="col">{{ __('ui.dashboard.col_status') }}</th><th scope="col">{{ __('ui.dashboard.col_count') }}</th></tr></thead>
        <tbody>@foreach($data['case_status_counts'] as $status => $count)<tr><td><span class="badge {{ $status }}">{{ __('ui.dashboard.status.'.$status) }}</span></td><td>{{ $count }}</td></tr>@endforeach</tbody>
    </table>
    @endif
</div>

@if($data['pending_review_posts'] > 0)
<div class="card"><h2>{{ __('ui.dashboard.pending_review_posts') }}</h2><p><a class="btn" href="{{ route('admin.cms.posts.index') }}">{{ $data['pending_review_posts'] }} {{ __('ui.dashboard.posts_in_review') }}</a></p></div>
@endif
@endsection
