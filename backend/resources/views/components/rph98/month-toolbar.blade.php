@props(['locale', 'monthQueryKey', 'previousMonth', 'nextMonth', 'monthLabel'])
<nav class="rph98-toolbar" aria-label="{{ __('panel.calendar.title') }}">
    <a class="rph98-btn" href="{{ route('panel.calendar.index', ['locale' => $locale, $monthQueryKey => $previousMonth]) }}">{{ __('panel.calendar.previous') }}</a>
    <p class="rph98-month">{{ $monthLabel }}</p>
    <a class="rph98-btn" href="{{ route('panel.calendar.index', ['locale' => $locale]) }}">{{ __('panel.calendar.today') }}</a>
    <a class="rph98-btn" href="{{ route('panel.calendar.index', ['locale' => $locale, $monthQueryKey => $nextMonth]) }}">{{ __('panel.calendar.next') }}</a>
</nav>
