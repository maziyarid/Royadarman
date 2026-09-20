@php
    $minutes = $minutes ?? null;
    $band = $band ?? 'unknown';
@endphp
@if($minutes !== null)
    <span class="sla-chip sla-{{ $band }}" title="{{ __('ui.dashboard.wait_note') }}">
        {{ __('ui.dashboard.waiting') }}
        ·
        @if($minutes < 60)
            {{ __('ui.dashboard.wait_minutes', ['minutes' => $minutes]) }}
        @else
            {{ __('ui.dashboard.wait_hours', ['hours' => intdiv($minutes, 60)]) }}
        @endif
    </span>
@endif
