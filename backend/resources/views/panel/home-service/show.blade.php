@extends('panel.layout')
@section('title', __('panel.nav.home_service'))
@section('heading', __('panel.nav.home_service'))
@section('actions')
    <a class="btn" href="{{ route('panel.home-service.index', ['locale' => $locale]) }}">{{ __('panel.support.back') }}</a>
@endsection
@section('content')
@php $status = $homeService->status instanceof \BackedEnum ? $homeService->status->value : $homeService->status; @endphp
<section class="card pad">
    <div class="facts">
        <div class="fact"><small>{{ __('panel.table.status') }}</small><span class="badge {{ $status }}">{{ __('ui.dashboard.home_status.'.$status) }}</span></div>
        <div class="fact"><small>{{ __('ui.dashboard.col_area') }}</small>{{ __('request.areas.'.$homeService->tehran_area) }}</div>
        @if($homeService->case)
            <div class="fact"><small>{{ __('panel.table.reference') }}</small>
                <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $homeService->case->id]) }}"><bdi>{{ $homeService->case->public_reference }}</bdi></a>
            </div>
        @endif
        @if($homeService->scheduled_for)
            <div class="fact"><small>{{ __('panel.home.scheduled') }}</small><bdi>{{ $homeService->scheduled_for }}</bdi></div>
        @endif
    </div>
    <p class="notice">{{ __('panel.home.tehran_only') }}</p>
    <p class="hint">{{ __('panel.home.not_every_procedure') }}</p>
</section>
@if($isDemo)
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@else
    @if($panelKey === 'patient' && $status === 'provider_accepted')
        <form method="post" action="{{ route('panel.home-service.confirm', ['locale' => $locale, 'homeService' => $homeService->id]) }}">
            @csrf
            <input type="hidden" name="version" value="{{ $homeService->version }}">
            <button class="btn primary" type="submit">{{ __('panel.home.confirm') }}</button>
        </form>
    @endif
    @if($panelKey === 'coordinator' && count($allowedTargets))
        <section class="card pad">
            <h2>{{ __('panel.home.transition') }}</h2>
            <form method="post" action="{{ route('panel.home-service.transition', ['locale' => $locale, 'homeService' => $homeService->id]) }}">
                @csrf
                <input type="hidden" name="version" value="{{ $homeService->version }}">
                <div class="field">
                    <label for="status">{{ __('panel.table.status') }}</label>
                    <select id="status" name="status" required>
                        @foreach($allowedTargets as $target)
                            <option value="{{ $target->value }}">{{ __('ui.dashboard.home_status.'.$target->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="reason">{{ __('panel_case.reason') }}</label>
                    <input id="reason" name="reason" maxlength="200">
                </div>
                <button class="btn primary" type="submit">{{ __('panel.home.transition') }}</button>
            </form>
        </section>
    @endif
@endif
@endsection
