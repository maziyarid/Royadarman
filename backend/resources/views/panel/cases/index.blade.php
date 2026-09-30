@extends('panel.layout')
@section('title', __('panel.cases.title'))
@section('heading', __('panel.cases.title'))

@section('content')
<section class="hero-panel">
    <h1>{{ __('panel.cases.title') }}</h1>
    <p>{{ __('panel.cases.intro') }}</p>
</section>

<form class="filters" method="get" role="search">
    <div class="field">
        <label for="case-q">{{ __('panel.cases.search') }}</label>
        <input id="case-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('panel.cases.search_placeholder') }}" autocomplete="off">
    </div>

    <div class="field">
        <label for="case-status">{{ __('panel.table.status') }}</label>
        <select id="case-status" name="status">
            <option value="">{{ __('panel.cases.all_statuses') }}</option>
            @foreach($statusOptions as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('ui.dashboard.status.'.$status) }}</option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="case-service">{{ __('panel.table.service') }}</label>
        <select id="case-service" name="service">
            <option value="">{{ __('panel.cases.all_services') }}</option>
            @foreach($serviceOptions as $service)
                <option value="{{ $service }}" @selected($filters['service'] === $service)>{{ __('ui.dashboard.service.'.$service) }}</option>
            @endforeach
        </select>
    </div>

    @if($isCoordinator)
        <div class="field">
            <label for="case-priority">{{ __('panel.cases.priority') }}</label>
            <select id="case-priority" name="priority">
                <option value="">{{ __('panel.cases.all_priorities') }}</option>
                <option value="normal" @selected($filters['priority'] === 'normal')>{{ __('panel.cases.priority_values.normal') }}</option>
                <option value="urgent" @selected($filters['priority'] === 'urgent')>{{ __('panel.cases.priority_values.urgent') }}</option>
            </select>
        </div>
    @endif

    <button class="btn primary" type="submit">{{ __('panel.filter_apply') }}</button>
    <a class="btn" href="{{ route('panel.cases.index', ['locale'=>$locale]) }}">{{ __('panel.cases.reset') }}</a>
</form>

<section class="card">
    <div class="card-head">
        <span>{{ __('panel.cases.assigned') }}</span>
        <span class="muted">{{ $cases->total() }}</span>
    </div>

    @if($cases->isEmpty())
        <div class="empty">{{ __('panel.cases.empty') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('panel.table.reference') }}</th>
                        <th scope="col">{{ __('panel.table.service') }}</th>
                        <th scope="col">{{ __('panel.table.status') }}</th>
                        @if($isCoordinator)
                            <th scope="col">{{ __('panel.cases.priority') }}</th>
                        @endif
                        <th scope="col">{{ __('panel.table.updated') }}</th>
                        <th scope="col">{{ __('panel.cases.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cases as $case)
                        @php
                            $status = $case->status instanceof BackedEnum ? $case->status->value : $case->status;
                            $service = $case->service_type instanceof BackedEnum ? $case->service_type->value : $case->service_type;
                        @endphp
                        <tr class="{{ $case->priority === 'urgent' ? 'row-attention' : '' }}">
                            <td><bdi>{{ $case->public_reference }}</bdi></td>
                            <td><span class="badge">{{ __('ui.dashboard.service.'.$service) }}</span></td>
                            <td><span class="badge {{ $status }}">{{ __('ui.dashboard.status.'.$status) }}</span></td>
                            @if($isCoordinator)
                                <td>
                                    <span class="badge {{ $case->priority === 'urgent' ? 'overdue' : '' }}">
                                        {{ __('panel.cases.priority_values.'.($case->priority ?: 'normal')) }}
                                    </span>
                                </td>
                            @endif
                            <td><bdi>{{ $case->updated_at }}</bdi></td>
                            <td><a class="btn sm" href="{{ route('panel.case', ['locale'=>$locale, 'case'=>$case->id]) }}">{{ __('panel.cases.open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $cases->links() }}</div>
    @endif
</section>

<p class="hint">{{ __('panel.cases.privacy_note') }}</p>
@endsection
