@props(['value', 'label'])
@php
    $displayValue = (string) $value;
    if (($locale ?? app()->getLocale()) === 'fa') {
        $displayValue = \App\Support\JalaliCalendar::toPersianDigits($displayValue);
    }
@endphp
<article class="rph98-stat">
    <div class="rph98-stat-num">{{ $displayValue }}</div>
    <div class="rph98-stat-label">{{ $label }}</div>
</article>
