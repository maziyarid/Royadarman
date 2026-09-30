@extends('panel.layout')
@section('title', __('panel.tasks.title'))
@section('heading', __('panel.tasks.title'))
@section('actions')
    <a class="btn primary" href="#new-task">{{ __('panel.tasks.new') }}</a>
@endsection

@section('content')
<section class="hero-panel">
    <h1>{{ __('panel.tasks.title') }}</h1>
    <p>{{ __('panel.tasks.intro') }}</p>
</section>

<section class="stat-grid">
    <article class="stat"><div class="num">{{ $summary['open'] }}</div><div class="label">{{ __('panel.tasks.status.open') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['in_progress'] }}</div><div class="label">{{ __('panel.tasks.status.in_progress') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['due_today'] }}</div><div class="label">{{ __('panel.tasks.due_today') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['overdue'] }}</div><div class="label">{{ __('panel.tasks.overdue') }}</div></article>
    <article class="stat"><div class="num">{{ $summary['done'] }}</div><div class="label">{{ __('panel.tasks.status.done') }}</div></article>
</section>

<form class="filters" method="get">
    <div class="field">
        <label for="task-status">{{ __('panel.table.status') }}</label>
        <select id="task-status" name="status">
            <option value="">{{ __('panel.tasks.all') }}</option>
            @foreach(['open','in_progress','done'] as $status)
                <option value="{{ $status }}" @selected($filterStatus === $status)>{{ __('panel.tasks.status.'.$status) }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn" type="submit">{{ __('panel.filter_apply') }}</button>
</form>

<div class="kanban-grid">
    @foreach(['open','in_progress','done'] as $column)
        <section class="kanban-col">
            <header class="kanban-head">
                <strong>{{ __('panel.tasks.status.'.$column) }}</strong>
                <span class="badge">{{ $tasks->getCollection()->where('status', $column)->count() }}</span>
            </header>

            <div class="kanban-list">
                @forelse($tasks->getCollection()->where('status', $column) as $task)
                    @php
                        $overdue = $task->status !== 'done' && $task->due_at?->isPast();
                        $service = $task->case?->service_type instanceof \BackedEnum ? $task->case->service_type->value : $task->case?->service_type;
                    @endphp
                    <article class="task-card {{ $overdue ? 'overdue-card' : '' }}">
                        <div class="task-card-main">
                            <a class="case-link" href="{{ route('panel.case', ['locale'=>$locale, 'case'=>$task->case_id]) }}">
                                <bdi>{{ $task->case?->public_reference }}</bdi>
                            </a>
                            <div class="task-meta">
                                <span class="badge">{{ __('panel.tasks.types.'.$task->task_type) }}</span>
                                @if($service)<span>{{ __('ui.dashboard.service.'.$service) }}</span>@endif
                            </div>
                            @if($task->operational_note)
                                <p>{{ $task->operational_note }}</p>
                            @endif
                            @if($task->due_at)
                                <small class="{{ $overdue ? 'danger-text' : 'muted' }}">
                                    {{ __('panel.tasks.due') }} · <bdi>{{ $task->due_at->timezone(config('royadarman.display_timezone'))->format('Y-m-d H:i') }}</bdi>
                                </small>
                            @endif
                        </div>

                        <details>
                            <summary>{{ __('panel.tasks.edit') }}</summary>
                            <form method="post" action="{{ route('panel.tasks.update', ['locale'=>$locale, 'task'=>$task->id]) }}" class="table-spaced">
                                @csrf
                                @method('PATCH')
                                <div class="field">
                                    <label>{{ __('panel.table.status') }}</label>
                                    <select name="status" required>
                                        @foreach(['open','in_progress','done'] as $status)
                                            <option value="{{ $status }}" @selected($task->status === $status)>{{ __('panel.tasks.status.'.$status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>{{ __('panel.tasks.note') }}</label>
                                    <textarea name="operational_note" maxlength="1000">{{ $task->operational_note }}</textarea>
                                </div>
                                <div class="field">
                                    <label>{{ __('panel.tasks.due') }}</label>
                                    <input type="datetime-local" name="due_at" value="{{ $task->due_at?->timezone(config('royadarman.display_timezone'))->format('Y-m-d\TH:i') }}">
                                </div>
                                <button class="btn" type="submit">{{ __('panel.tasks.save') }}</button>
                            </form>
                            <form method="post" action="{{ route('panel.tasks.destroy', ['locale'=>$locale, 'task'=>$task->id]) }}" data-confirm="{{ __('panel.tasks.delete_confirm') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger sm" type="submit">{{ __('panel.tasks.delete') }}</button>
                            </form>
                        </details>
                    </article>
                @empty
                    <div class="empty">{{ __('panel.tasks.empty') }}</div>
                @endforelse
            </div>
        </section>
    @endforeach
</div>

<div class="card-body">{{ $tasks->links() }}</div>

<section class="card pad" id="new-task">
    <h2>{{ __('panel.tasks.new') }}</h2>
    @if($cases->isEmpty())
        <div class="empty">{{ __('panel.tasks.no_cases') }}</div>
    @else
        <form method="post" action="{{ route('panel.tasks.store', ['locale'=>$locale]) }}">
            @csrf
            <div class="grid grid-2">
                <div class="field">
                    <label for="task-case">{{ __('panel.tasks.case') }}</label>
                    <select id="task-case" name="case_id" required>
                        @foreach($cases as $case)
                            @php $service = $case->service_type instanceof \BackedEnum ? $case->service_type->value : $case->service_type; @endphp
                            <option value="{{ $case->id }}">{{ $case->public_reference }} · {{ __('ui.dashboard.service.'.$service) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="task-type">{{ __('panel.tasks.type') }}</label>
                    <select id="task-type" name="task_type" required>
                        @foreach(['follow_up','support','referral','home_service','document','other'] as $type)
                            <option value="{{ $type }}">{{ __('panel.tasks.types.'.$type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="task-due">{{ __('panel.tasks.due') }}</label>
                    <input id="task-due" type="datetime-local" name="due_at">
                </div>
            </div>
            <div class="field">
                <label for="task-note">{{ __('panel.tasks.note') }}</label>
                <textarea id="task-note" name="operational_note" maxlength="1000" placeholder="{{ __('panel.tasks.note_hint') }}"></textarea>
            </div>
            <button class="btn primary" type="submit">{{ __('panel.tasks.create') }}</button>
        </form>
    @endif
</section>
@endsection
