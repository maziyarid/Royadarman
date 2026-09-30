@extends('panel.layout')
@section('title', __('panel.calendar.title'))
@section('heading', __('panel.calendar.title'))

@section('content')
<section class="hero-panel">
    <h1>{{ __('panel.calendar.title') }}</h1>
    <p>{{ __('panel.calendar.intro') }}</p>
</section>

<div class="calendar-toolbar">
    <a class="btn" href="{{ route('panel.calendar.index', ['locale'=>$locale, $monthQueryKey=>$previousMonth]) }}">← {{ __('panel.calendar.previous') }}</a>
    <strong>{{ $jalaliMode ? $monthLabel : $monthInput }}</strong>
    <a class="btn" href="{{ route('panel.calendar.index', ['locale'=>$locale, $monthQueryKey=>$nextMonth]) }}">{{ __('panel.calendar.next') }} →</a>
</div>

<section class="stat-grid">
    <article class="stat"><div class="num">{{ $summary['total'] }}</div><div class="label">{{ __('panel.calendar.total') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['tasks'] }}</div><div class="label">{{ __('panel.calendar.types.task') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['home_service'] }}</div><div class="label">{{ __('panel.calendar.types.home_service') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['referral_expiry'] }}</div><div class="label">{{ __('panel.calendar.types.referral_deadline') }}</div></article>
</section>

<section class="calendar-grid" aria-label="{{ __('panel.calendar.title') }}">
    @if($jalaliMode)
        @foreach($weekdays as $weekday)
            <div class="calendar-weekday">{{ $weekday }}</div>
        @endforeach
    @endif
    @foreach(range(1, $leading) as $unused)
        <div class="calendar-day calendar-day-empty" aria-hidden="true"></div>
    @endforeach

    @foreach($days as $day)
        <article class="calendar-day {{ $day['today'] ? 'today' : '' }} {{ $jalaliMode && $day['friday'] ? 'friday' : '' }}">
            <header>
                <strong>{{ $day['day_number'] }}</strong>
                <small>{{ $jalaliMode ? $day['weekday'] : $day['date']->locale($locale)->isoFormat('ddd') }}</small>
            </header>

            @if($day['events']->isEmpty())
                <span class="calendar-no-event">—</span>
            @else
                <div class="calendar-events">
                    @foreach($day['events'] as $event)
                        <a class="calendar-event {{ $event['kind'] }}" href="{{ $event['url'] }}">
                            <span class="calendar-event-time" dir="ltr">{{ $event['at']->format('H:i') }}</span>
                            <strong>{{ $event['title'] }}</strong>
                            <small>{{ $event['meta'] }}</small>
                        </a>
                    @endforeach
                </div>
            @endif
        </article>
    @endforeach
</section>

<section class="card pad">
    <h2>{{ __('panel.calendar.agenda') }}</h2>
    @forelse($events as $event)
        <a class="agenda-row" href="{{ $event['url'] }}">
            <time dir="ltr">@if($jalaliMode)@php([$eventYear, $eventMonth, $eventDay] = \App\Support\JalaliCalendar::fromGregorian((int) $event['at']->format('Y'), (int) $event['at']->format('m'), (int) $event['at']->format('d'))){{ \App\Support\JalaliCalendar::toPersianDigits(sprintf('%04d-%02d-%02d %s', $eventYear, $eventMonth, $eventDay, $event['at']->format('H:i'))) }}@else{{ $event['at']->format('Y-m-d H:i') }}@endif</time>
            <div>
                <strong>{{ $event['title'] }}</strong>
                <span>{{ $event['meta'] }}</span>
            </div>
            <span class="badge">{{ __('panel.calendar.kinds.'.$event['kind']) }}</span>
        </a>
    @empty
        <div class="empty">{{ __('panel.calendar.empty') }}</div>
    @endforelse
</section>

<p class="hint">{{ __('panel.calendar.privacy_note') }}</p>
@endsection
