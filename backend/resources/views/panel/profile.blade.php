@extends('panel.layout')
@section('title', __('panel.nav.profile'))
@section('heading', __('panel.nav.profile'))
@section('content')
<section class="card pad">
    <div class="facts">
        <div class="fact"><small>{{ __('panel.profile.role') }}</small>{{ __('ui.dashboard.roles.'.$panelKey) }}</div>
        <div class="fact"><small>{{ __('panel.profile.locale') }}</small>{{ strtoupper($profileUser->locale) }}</div>
        @if($profileUser->name)
            <div class="fact"><small>{{ __('panel.profile.name') }}</small>{{ $profileUser->name }}</div>
        @endif
    </div>
</section>
@if($isDemo)
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@else
    <section class="card pad">
        <h2>{{ __('panel.profile.preferences') }}</h2>
        <form method="post" action="{{ route('panel.profile.update', ['locale' => $locale]) }}">
            @csrf
            @method('PATCH')
            <div class="field">
                <label for="name">{{ __('panel.profile.name') }}</label>
                <input id="name" name="name" maxlength="80" value="{{ old('name', $profileUser->name) }}">
            </div>
            <div class="field">
                <label for="pref-locale">{{ __('panel.profile.locale') }}</label>
                <select id="pref-locale" name="locale" required>
                    @foreach(['fa' => 'فارسی', 'ar' => 'العربية', 'en' => 'English'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $profileUser->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn primary" type="submit">{{ __('panel.profile.save') }}</button>
        </form>
    </section>
@endif
@endsection
