@extends('panel.layout')
@section('title', $conversation->subject ?: __('panel.nav.support'))
@section('heading', $conversation->subject ?: __('panel.support.untitled'))
@section('actions')
    <a class="btn" href="{{ route('panel.support.index', ['locale' => $locale]) }}">{{ __('panel.support.back') }}</a>
@endsection
@section('content')
@php
    $status = $conversation->status instanceof \BackedEnum ? $conversation->status->value : $conversation->status;
    $cat = $conversation->category instanceof \BackedEnum ? $conversation->category->value : $conversation->category;
@endphp
<section class="card pad">
    <div class="facts">
        <div class="fact"><small>{{ __('panel.table.status') }}</small><span class="badge {{ $status }}">{{ __('panel.support.status.'.$status) }}</span></div>
        <div class="fact"><small>{{ __('panel.support.category') }}</small>{{ __('panel.support.category_values.'.$cat) }}</div>
        @if($conversation->case)
            <div class="fact"><small>{{ __('panel.table.reference') }}</small>
                <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $conversation->case->id]) }}"><bdi>{{ $conversation->case->public_reference }}</bdi></a>
            </div>
        @endif
    </div>
</section>
<section class="card pad">
    <h2>{{ __('panel.support.messages') }}</h2>
    @forelse($messages as $message)
        <article class="item">
            <div class="muted">
                {{ $message->is_internal ? __('panel.support.internal') : __('panel.support.message') }}
                · <bdi>{{ $message->created_at }}</bdi>
            </div>
            <p dir="auto">{{ $message->body }}</p>
        </article>
    @empty
        <p class="muted">{{ __('panel.support.no_messages') }}</p>
    @endforelse
</section>
@if($isDemo)
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@else
    @can('reply', $conversation)
        <section class="card pad">
            <h2>{{ __('panel.support.reply') }}</h2>
            <form method="post" action="{{ route('panel.support.reply', ['locale' => $locale, 'conversation' => $conversation->id]) }}">
                @csrf
                <div class="field">
                    <label for="message">{{ __('panel.support.message') }}</label>
                    <textarea id="message" name="message" required maxlength="5000" dir="auto"></textarea>
                </div>
                <button class="btn primary" type="submit">{{ __('panel.support.send') }}</button>
            </form>
        </section>
    @endcan
    @can('assign', $conversation)
        @if($conversation->assignee_user_id === null && $panelKey === 'coordinator')
            <form method="post" action="{{ route('panel.support.assign', ['locale' => $locale, 'conversation' => $conversation->id]) }}">
                @csrf
                <button class="btn" type="submit">{{ __('panel.support.take') }}</button>
            </form>
        @endif
    @endcan
    @can('changeStatus', $conversation)
        <section class="card pad">
            <h2>{{ __('panel.support.change_status') }}</h2>
            <form method="post" action="{{ route('panel.support.status', ['locale' => $locale, 'conversation' => $conversation->id]) }}">
                @csrf
                <div class="field">
                    <label for="new-status">{{ __('panel.table.status') }}</label>
                    <select id="new-status" name="status" required>
                        @foreach($conversation->status->allowedTargets() as $target)
                            <option value="{{ $target->value }}">{{ __('panel.support.status.'.$target->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="reason">{{ __('panel_case.reason') }}</label>
                    <input id="reason" name="reason" maxlength="200">
                </div>
                <button class="btn primary" type="submit">{{ __('panel.support.change_status') }}</button>
            </form>
        </section>
    @endcan
@endif
@endsection
