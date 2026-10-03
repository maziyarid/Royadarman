@extends('panel.layout')
@section('title', __('panel.calendar.title'))
@section('heading', __('panel.calendar.title'))

@push('scripts')
<script src="/assets/rph98-calendar.js?v=20260930" defer></script>
@endpush
@section('content')
<link rel="stylesheet" href="/assets/rph98-presentation.css?v=20260930-worker-b">
<div class="rph98-calendar" data-rph98-calendar>
    <section class="rph98-hero">
        <h2>{{ __('panel.calendar.title') }}</h2>
        <p>{{ __('panel.calendar.intro') }}</p>
    </section>

    <x-rph98.month-toolbar
        :locale="$locale"
        :month-query-key="$monthQueryKey"
        :previous-month="$previousMonth"
        :next-month="$nextMonth"
        :month-label="$jalaliMode ? $monthLabel : $monthInput"
    />

    <x-rph98.filter-chips
        :label="__('panel.calendar.total')"
        :chips="[
            ['id' => 'all', 'label' => __('panel.calendar.total')],
            ['id' => 'task', 'label' => __('panel.calendar.types.task')],
            ['id' => 'home_service', 'label' => __('panel.calendar.types.home_service')],
            ['id' => 'referral_expiry', 'label' => __('panel.calendar.types.referral_deadline')],
        ]"
    />

    <section class="rph98-stats" aria-label="{{ __('panel.calendar.total') }}">
        <x-rph98.stat :value="$summary['total']" :label="__('panel.calendar.total')" />
        <x-rph98.stat :value="$summary['tasks']" :label="__('panel.calendar.types.task')" />
        <x-rph98.stat :value="$summary['home_service']" :label="__('panel.calendar.types.home_service')" />
        <x-rph98.stat :value="$summary['referral_expiry']" :label="__('panel.calendar.types.referral_deadline')" />
    </section>

    <x-rph98.operations-month
        :days="$days"
        :leading="$leading"
        :weekdays="$weekdays"
        :jalali-mode="$jalaliMode"
        :locale="$locale"
    />

    <x-rph98.operations-agenda :events="$events" :jalali-mode="$jalaliMode" />

    <p class="rph98-note">{{ __('panel.calendar.privacy_note') }}</p>
</div>

@endsection
