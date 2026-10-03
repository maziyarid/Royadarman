@props(['events', 'jalaliMode'])
<section class="rph98-agenda-card" aria-labelledby="rph98-agenda-title">
    <h2 id="rph98-agenda-title">{{ __('panel.calendar.total') }}</h2>
    <div data-rph98-agenda>
        @forelse($events as $event)
            @php
                $kindLabel = match ($event['kind']) {
                    'task' => __('panel.calendar.types.task'),
                    'home_service' => __('panel.calendar.types.home_service'),
                    'referral_expiry' => __('panel.calendar.types.referral_deadline'),
                    default => $event['kind'],
                };
            @endphp
            <a class="rph98-agenda-row" href="{{ $event['url'] }}" data-rph98-event data-kind="{{ $event['kind'] }}">
                <time dir="ltr">@if($jalaliMode)@php([$eventYear, $eventMonth, $eventDay] = \App\Support\JalaliCalendar::fromGregorian((int) $event['at']->format('Y'), (int) $event['at']->format('m'), (int) $event['at']->format('d'))){{ \App\Support\JalaliCalendar::toPersianDigits(sprintf('%04d-%02d-%02d %s', $eventYear, $eventMonth, $eventDay, $event['at']->format('H:i'))) }}@else{{ $event['at']->format('Y-m-d H:i') }}@endif</time>
                <span class="rph98-agenda-copy">
                    <strong><bdi>{{ $event['title'] }}</bdi></strong>
                    <span><bdi>{{ $event['meta'] }}</bdi></span>
                </span>
                <span class="rph98-badge rph98-kind-{{ $event['kind'] }}">{{ $kindLabel }}</span>
            </a>
        @empty
            <x-rph98.empty-state :message="__('panel.calendar.empty')" />
        @endforelse
    </div>
    <div data-rph98-filter-empty hidden>
        <x-rph98.empty-state :message="__('panel.calendar.empty')" />
    </div>
</section>
