@extends('panel.layout')
@section('title', __('panel.calendar.title'))
@section('heading', __('panel.calendar.title'))

@section('content')
<link rel="stylesheet" href="/assets/rph98-presentation.css?v=20260930-worker-b">
<div class="rph98-calendar" data-rph98-calendar>
    <section class="rph98-hero">
        <h1>{{ __('panel.calendar.title') }}</h1>
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
<script>
(function () {
    var root = document.querySelector('[data-rph98-calendar]');
    if (!root) return;
    var buttons = root.querySelectorAll('[data-rph98-filter]');
    var filterEmpty = root.querySelector('[data-rph98-filter-empty]');
    function apply(kind) {
        root.querySelectorAll('[data-rph98-event]').forEach(function (el) {
            var show = kind === 'all' || el.getAttribute('data-kind') === kind;
            if (show) el.removeAttribute('hidden');
            else el.setAttribute('hidden', '');
        });
        buttons.forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.getAttribute('data-rph98-filter') === kind ? 'true' : 'false');
        });
        if (!filterEmpty) return;
        var total = root.querySelectorAll('[data-rph98-agenda] [data-rph98-event]').length;
        var visible = root.querySelectorAll('[data-rph98-agenda] [data-rph98-event]:not([hidden])').length;
        if (total > 0 && visible === 0) filterEmpty.removeAttribute('hidden');
        else filterEmpty.setAttribute('hidden', '');
    }
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            apply(btn.getAttribute('data-rph98-filter'));
        });
    });
})();
</script>
@endsection
