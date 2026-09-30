@extends('panel.layout')
@section('title', $conversation->subject ?: __('panel.nav.support'))
@section('heading', $conversation->subject ?: __('panel.support.untitled'))
@section('actions')
    <a class="btn" href="{{ route('panel.support.index', ['locale' => $locale]) }}">{{ __('panel.support.back') }}</a>
@endsection

@section('content')
@php
    $status = $conversation->status instanceof BackedEnum ? $conversation->status->value : $conversation->status;
    $cat = $conversation->category instanceof BackedEnum ? $conversation->category->value : $conversation->category;
    $priority = $conversation->priority instanceof BackedEnum ? $conversation->priority->value : $conversation->priority;
@endphp

<section class="card pad">
    <div class="facts">
        <div class="fact"><small>{{ __('panel.table.status') }}</small><span class="badge {{ $status }}">{{ __('panel.support.status.'.$status) }}</span></div>
        <div class="fact"><small>{{ __('panel.support.priority') }}</small><span class="badge priority-{{ $priority }}">{{ __('panel.support.priority_values.'.$priority) }}</span></div>
        <div class="fact"><small>{{ __('panel.support.category') }}</small>{{ __('panel.support.category_values.'.$cat) }}</div>
        @if($panelKey !== 'patient')
            <div class="fact"><small>{{ __('panel.support.assignment') }}</small>{{ $conversation->assignee?->name ?: __('panel.support.unassigned') }}</div>
        @endif
        @if($conversation->case)
            <div class="fact"><small>{{ __('panel.table.reference') }}</small>
                <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $conversation->case->id]) }}"><bdi>{{ $conversation->case->public_reference }}</bdi></a>
            </div>
        @endif
        <div class="fact"><small>{{ __('panel.support.opened_at') }}</small><bdi>{{ $conversation->opened_at }}</bdi></div>
        <div class="fact"><small>{{ __('panel.support.first_response') }}</small>
            @if($conversation->first_response_at)
                <bdi>{{ $conversation->opened_at->diffInMinutes($conversation->first_response_at) }}</bdi> {{ __('panel.support.minutes') }}
            @else
                {{ __('panel.support.not_yet') }}
            @endif
        </div>
    </div>
</section>

<section class="card pad">
    <h2>{{ __('panel.support.messages') }}</h2>
    @forelse($messages as $message)
        <article class="support-message {{ $message->is_internal ? 'internal-note' : '' }}">
            <div class="support-message-meta">
                <span>
                    {{ $message->is_internal ? __('panel.support.internal') : __('panel.support.message') }}
                    @if($message->author?->name) · {{ $message->author->name }} @endif
                </span>
                <bdi>{{ $message->created_at }}</bdi>
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
    <div class="workspace-two-col">
        <div>
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

            @can('addInternalNote', $conversation)
                <section class="card pad">
                    <h2>{{ __('panel.support.add_internal_note') }}</h2>
                    <p class="hint">{{ __('panel.support.internal_note_help') }}</p>
                    <form method="post" action="{{ route('panel.support.internal-note', ['locale' => $locale, 'conversation' => $conversation->id]) }}">
                        @csrf
                        <div class="field">
                            <label for="internal-message">{{ __('panel.support.internal') }}</label>
                            <textarea id="internal-message" name="message" required maxlength="5000" dir="auto"></textarea>
                        </div>
                        <button class="btn" type="submit">{{ __('panel.support.save_note') }}</button>
                    </form>
                </section>
            @endcan
        </div>

        <aside>
            @can('assign', $conversation)
                <section class="card pad">
                    <h2>{{ __('panel.support.assignment') }}</h2>
                    @if($panelKey === 'coordinator')
                        @if((int) $conversation->assignee_user_id !== (int) auth()->id())
                            <form method="post" action="{{ route('panel.support.assign', ['locale' => $locale, 'conversation' => $conversation->id]) }}">
                                @csrf
                                <button class="btn primary" type="submit">{{ __('panel.support.take') }}</button>
                            </form>
                        @else
                            <p class="notice success">{{ __('panel.support.assigned_to_you') }}</p>
                        @endif
                    @elseif($panelKey === 'owner')
                        <form method="post" action="{{ route('panel.support.assign', ['locale' => $locale, 'conversation' => $conversation->id]) }}">
                            @csrf
                            <div class="field">
                                <label for="assignee-user">{{ __('panel.support.coordinator') }}</label>
                                <select id="assignee-user" name="assignee_user_id">
                                    <option value="">{{ __('panel.support.unassigned') }}</option>
                                    @foreach($activeCoordinators as $coordinator)
                                        <option value="{{ $coordinator->id }}" @selected((int)$conversation->assignee_user_id === (int)$coordinator->id)>{{ $coordinator->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="btn" type="submit">{{ __('panel.support.save_assignment') }}</button>
                        </form>
                    @endif
                </section>
            @endcan

            @can('changePriority', $conversation)
                <section class="card pad">
                    <h2>{{ __('panel.support.priority') }}</h2>
                    <form method="post" action="{{ route('panel.support.priority', ['locale' => $locale, 'conversation' => $conversation->id]) }}">
                        @csrf
                        <div class="field">
                            <label for="conversation-priority">{{ __('panel.support.priority') }}</label>
                            <select id="conversation-priority" name="priority" required>
                                @foreach(['low','normal','high','urgent'] as $value)
                                    <option value="{{ $value }}" @selected($priority === $value)>{{ __('panel.support.priority_values.'.$value) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="btn" type="submit">{{ __('panel.support.save_priority') }}</button>
                    </form>
                </section>
            @endcan

            @can('changeStatus', $conversation)
                <section class="card pad">
                    <h2>{{ __('panel.support.change_status') }}</h2>
                    @if(count($conversation->status->allowedTargets()) > 0)
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
                    @else
                        <p class="hint">{{ __('panel.support.no_status_actions') }}</p>
                    @endif
                </section>
            @endcan
        </aside>
    </div>
@endif
@endsection
