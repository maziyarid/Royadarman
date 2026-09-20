@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
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
    <div class="card-head">{{ __('ui.dashboard.case_status_breakdown') }}</div>
    @if($data['case_status_counts']->isEmpty())
        <div class="empty">{{ __('ui.dashboard.no_cases') }}</div>
    @else
        <div class="task-grid compact">
            @foreach($data['case_status_counts'] as $status => $count)
                <article class="task-card">
                    <span class="badge {{ $status }}">{{ __('ui.dashboard.status.'.$status) }}</span>
                    <strong>{{ $count }}</strong>
                </article>
            @endforeach
        </div>
    @endif
</div>
@if($data['pending_review_posts'] > 0)
    <a class="task-card action" href="{{ route('admin.cms.posts.index') }}">
        <strong>{{ __('ui.dashboard.pending_review_posts') }}</strong>
        <small>{{ $data['pending_review_posts'] }} {{ __('ui.dashboard.posts_in_review') }}</small>
    </a>
@endif
<p class="notice">{{ __('panel.roles.owner.subtitle') }}</p>
@endsection
