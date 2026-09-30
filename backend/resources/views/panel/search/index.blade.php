@extends('panel.layout')
@section('title', __('panel.search.title'))
@section('heading', __('panel.search.title'))

@section('content')
<section class="hero-panel">
    <h1>{{ __('panel.search.title') }}</h1>
    <p>{{ __('panel.search.intro') }}</p>
</section>

<form class="workspace-search-page" method="get" role="search">
    <label class="sr-only" for="workspace-search-page">{{ __('panel.search.label') }}</label>
    <input id="workspace-search-page" type="search" name="q" value="{{ $query }}" placeholder="{{ __('panel.search.placeholder') }}" autocomplete="off" autofocus>
    <button class="btn primary" type="submit">{{ __('panel.search.submit') }}</button>
</form>

@if($query === '')
    <div class="empty">{{ __('panel.search.start') }}</div>
@elseif($results->isEmpty())
    <div class="empty">{{ __('panel.search.empty') }}</div>
@else
    <section class="search-results" aria-label="{{ __('panel.search.results') }}">
        @foreach($results as $result)
            <a class="search-result-card" href="{{ $result['url'] }}">
                <div>
                    <strong>{{ $result['title'] }}</strong>
                    <span>{{ $result['meta'] }}</span>
                </div>
                <span class="search-result-type">{{ __('panel.search.types.'.$result['type']) }}</span>
            </a>
        @endforeach
    </section>
@endif

<p class="hint">{{ __('panel.search.privacy_note') }}</p>
@endsection
