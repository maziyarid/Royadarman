@extends('panel.layout')
@section('title', __('panel.nav.support'))
@section('heading', __('panel.nav.support'))
@section('actions')
    @if(!$isDemo && $panelKey === 'patient')
        <a class="btn primary" href="#new-thread">{{ __('panel.support.new') }}</a>
    @endif
@endsection

@section('content')
<section class="hero-panel">
    <h1>{{ __('panel.support.workspace_title') }}</h1>
    <p>{{ __('panel.support.workspace_intro') }}</p>
</section>

<section class="stat-grid">
    <article class="stat"><div class="num">{{ $summary['open'] }}</div><div class="label">{{ __('panel.support.open_count') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['awaiting_patient'] }}</div><div class="label">{{ __('panel.support.awaiting_count') }}</div></article>
    @if($panelKey !== 'patient')
        <article class="stat"><div class="num">{{ $summary['unassigned'] }}</div><div class="label">{{ __('panel.support.unassigned_count') }}</div></article>
        <article class="stat"><div class="num">{{ $summary['urgent'] }}</div><div class="label">{{ __('panel.support.urgent_count') }}</div></article>
    @endif
</section>

<form class="filters" method="get" role="search">
    <div class="field">
        <label for="support-q">{{ __('panel.support.search') }}</label>
        <input id="support-q" name="q" type="search" value="{{ $filters['q'] }}" placeholder="{{ __('panel.support.search_placeholder') }}" autocomplete="off">
    </div>
    <div class="field">
        <label for="support-status">{{ __('panel.table.status') }}</label>
        <select id="support-status" name="status">
            <option value="">{{ __('panel.support.all_status') }}</option>
            @foreach(['open','in_progress','awaiting_patient','resolved','closed','reopened'] as $st)
                <option value="{{ $st }}" @selected($filters['status'] === $st)>{{ __('panel.support.status.'.$st) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="support-category">{{ __('panel.support.category') }}</label>
        <select id="support-category" name="category">
            <option value="">{{ __('panel.support.all_categories') }}</option>
            @foreach(['general','technical','coordination','clinical_question','referral','billing'] as $cat)
                <option value="{{ $cat }}" @selected($filters['category'] === $cat)>{{ __('panel.support.category_values.'.$cat) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="support-priority">{{ __('panel.support.priority') }}</label>
        <select id="support-priority" name="priority">
            <option value="">{{ __('panel.support.all_priorities') }}</option>
            @foreach(['low','normal','high','urgent'] as $priority)
                <option value="{{ $priority }}" @selected($filters['priority'] === $priority)>{{ __('panel.support.priority_values.'.$priority) }}</option>
            @endforeach
        </select>
    </div>
    @if($panelKey !== 'patient')
        <div class="field">
            <label for="support-assignment">{{ __('panel.support.assignment') }}</label>
            <select id="support-assignment" name="assignment">
                <option value="">{{ __('panel.support.all_assignments') }}</option>
                <option value="mine" @selected($filters['assignment'] === 'mine')>{{ __('panel.support.mine') }}</option>
                <option value="unassigned" @selected($filters['assignment'] === 'unassigned')>{{ __('panel.support.unassigned') }}</option>
            </select>
        </div>
    @endif
    <button class="btn primary" type="submit">{{ __('panel.filter_apply') }}</button>
    <a class="btn" href="{{ route('panel.support.index', ['locale'=>$locale]) }}">{{ __('panel.support.reset') }}</a>
</form>

<section class="card">
    <div class="card-head"><span>{{ __('panel.nav.support') }}</span><span class="muted">{{ $conversations->total() }}</span></div>
    @if($conversations->isEmpty())
        @php $hasFilters = collect($filters)->filter(fn ($value) => $value !== '')->isNotEmpty(); @endphp
        <div class="empty">{{ $hasFilters ? __('panel.support.empty_filtered') : __('panel.support.empty') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('panel.support.subject') }}</th>
                        <th scope="col">{{ __('panel.support.priority') }}</th>
                        <th scope="col">{{ __('panel.support.category') }}</th>
                        <th scope="col">{{ __('panel.table.status') }}</th>
                        @if($panelKey !== 'patient')
                            <th scope="col">{{ __('panel.support.assignment') }}</th>
                        @endif
                        <th scope="col">{{ __('panel.support.messages') }}</th>
                        <th scope="col">{{ __('panel.table.updated') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($conversations as $c)
                        @php
                            $status = $c->status instanceof BackedEnum ? $c->status->value : $c->status;
                            $cat = $c->category instanceof BackedEnum ? $c->category->value : $c->category;
                            $priority = $c->priority instanceof BackedEnum ? $c->priority->value : $c->priority;
                        @endphp
                        <tr class="{{ in_array($priority, ['urgent','high'], true) ? 'row-attention' : '' }}">
                            <td>
                                <a class="case-link" href="{{ route('panel.support.show', ['locale' => $locale, 'conversation' => $c->id]) }}">
                                    {{ $c->subject ?: __('panel.support.untitled') }}
                                </a>
                                @if($c->case)
                                    <div class="muted"><bdi>{{ $c->case->public_reference }}</bdi></div>
                                @endif
                            </td>
                            <td><span class="badge priority-{{ $priority }}">{{ __('panel.support.priority_values.'.$priority) }}</span></td>
                            <td>{{ __('panel.support.category_values.'.$cat) }}</td>
                            <td><span class="badge {{ $status }}">{{ __('panel.support.status.'.$status) }}</span></td>
                            @if($panelKey !== 'patient')
                                <td>{{ $c->assignee?->name ?: __('panel.support.unassigned') }}</td>
                            @endif
                            <td><bdi>{{ $c->message_count }}</bdi></td>
                            <td><bdi>{{ $c->updated_at }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $conversations->links() }}</div>
    @endif
</section>

@if(!$isDemo && $panelKey === 'patient')
    <section class="card pad" id="new-thread">
        <h2>{{ __('panel.support.new') }}</h2>
        <form method="post" action="{{ route('panel.support.store', ['locale' => $locale]) }}">
            @csrf
            <div class="field">
                <label for="subject">{{ __('panel.support.subject') }}</label>
                <input id="subject" name="subject" maxlength="200">
            </div>
            <div class="field">
                <label for="category">{{ __('panel.support.category') }}</label>
                <select id="category" name="category" required>
                    @foreach(['general','coordination','referral','technical'] as $cat)
                        <option value="{{ $cat }}">{{ __('panel.support.category_values.'.$cat) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="message">{{ __('panel.support.message') }}</label>
                <textarea id="message" name="message" required maxlength="5000"></textarea>
            </div>
            <button class="btn primary" type="submit">{{ __('panel.support.send') }}</button>
        </form>
    </section>
@elseif($isDemo && $panelKey === 'patient')
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@endif
@endsection
