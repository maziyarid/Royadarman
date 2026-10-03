@props(['days', 'leading', 'weekdays', 'jalaliMode', 'locale'])
<section class="rph98-month-grid" aria-label="{{ __('panel.calendar.title') }}">
    @if($jalaliMode)
        <div class="rph98-weekdays">
            @foreach($weekdays as $weekday)
                <div class="rph98-weekday">{{ $weekday }}</div>
            @endforeach
        </div>
    @endif
    <div class="rph98-grid">
        @for($blank = 0; $blank < $leading; $blank++)
            <div class="rph98-day rph98-day-empty" aria-hidden="true"></div>
        @endfor
        @foreach($days as $day)
            <article
                class="rph98-day {{ $day['today'] ? 'is-today' : '' }} {{ $jalaliMode && $day['friday'] ? 'is-friday' : '' }}"
                @if($day['today']) aria-current="date" @endif
            >
                <header>
                    <strong>{{ $day['day_number'] }}</strong>
                    <small>{{ $jalaliMode ? $day['weekday'] : $day['date']->locale($locale)->isoFormat('ddd') }}</small>
                    @if($day['today'])
                        <span class="rph98-sr">{{ __('panel.calendar.today') }}</span>
                    @endif
                </header>
                @if($day['events']->isEmpty())
                    <span class="rph98-no-event" aria-hidden="true">—</span>
                @else
                    <div class="rph98-events">
                        @foreach($day['events'] as $event)
                            @php
                                $clock = $event['at']->format('H:i');
                                if ($jalaliMode) {
                                    $clock = \App\Support\JalaliCalendar::toPersianDigits($clock);
                                }
                            @endphp
                            <a class="rph98-event rph98-kind-{{ $event['kind'] }}" href="{{ $event['url'] }}" data-rph98-event data-kind="{{ $event['kind'] }}">
                                <span class="rph98-event-time" dir="ltr">{{ $clock }}</span>
                                <span class="rph98-event-title"><bdi>{{ $event['title'] }}</bdi></span>
                                <span class="rph98-event-meta"><bdi>{{ $event['meta'] }}</bdi></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</section>
