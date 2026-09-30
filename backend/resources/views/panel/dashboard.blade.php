@extends('panel.layout')
@section('title', __('panel.roles.'.$panelKey.'.title'))
@section('heading', __('panel.roles.'.$panelKey.'.title'))
@section('actions')
    @if($panelKey === 'patient')
        <a class="btn primary" href="{{ route('patient.request.create', ['locale' => app()->getLocale()]) }}">{{ __('request.title') }}</a>
    @endif
@endsection
@section('content')
<section class="hero-panel">
    <h2>{{ __('panel.roles.'.$panelKey.'.title') }}</h2>
    <p>{{ __('panel.roles.'.$panelKey.'.subtitle') }}</p>
</section>
<nav class="dashboard-shortcuts" aria-label="{{ __('panel.nav.overview') }}">
    <a href="{{ route('panel.profile', ['locale' => app()->getLocale()]) }}">{{ __('panel.nav.profile') }} <span aria-hidden="true">↗</span></a>
    @if(in_array($panelKey, ['patient', 'coordinator', 'owner', 'tech_admin'], true))
        <a href="{{ route('panel.support.index', ['locale' => app()->getLocale()]) }}">{{ __('panel.nav.support') }} <span aria-hidden="true">↗</span></a>
    @endif
    @if($panelKey === 'coordinator' && empty($isDemo))
        <a href="{{ route('panel.tasks.index', ['locale' => app()->getLocale()]) }}">{{ __('panel.nav.tasks') }} <span aria-hidden="true">↗</span></a>
        <a href="{{ route('panel.calendar.index', ['locale' => app()->getLocale()]) }}">{{ __('panel.nav.calendar') }} <span aria-hidden="true">↗</span></a>
    @endif
    @if($panelKey === 'owner' && empty($isDemo))
        <a href="{{ route('panel.analytics.index', ['locale' => app()->getLocale()]) }}">{{ __('panel.nav.analytics') }} <span aria-hidden="true">↗</span></a>
    @endif
</nav>
<section class="metric-grid" aria-label="{{ __('panel.table.status') }}">
    @foreach($metrics as $key => $value)
        <article class="metric">
            <strong>{{ number_format((int) $value) }}</strong>
            <span>{{ __('panel.metrics.'.$key) }}</span>
        </article>
    @endforeach
</section>
@if($cases->isNotEmpty())
    <section class="card">
        <div class="card-head">
            <span>{{ __('panel.table.reference') }}</span>
            <span class="muted">{{ $cases->count() }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('panel.table.reference') }}</th>
                        <th scope="col">{{ __('panel.table.service') }}</th>
                        <th scope="col">{{ __('panel.table.status') }}</th>
                        <th scope="col">{{ __('panel.table.updated') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cases as $case)
                        @php
                            $service = $case->service_type instanceof \BackedEnum ? $case->service_type->value : $case->service_type;
                            $status = $case->status instanceof \BackedEnum ? $case->status->value : $case->status;
                        @endphp
                        <tr>
                            <td>
                                <a class="case-link" href="{{ route('panel.case', ['locale' => app()->getLocale(), 'case' => $case->id]) }}">
                                    <bdi>{{ $case->public_reference }}</bdi>
                                </a>
                            </td>
                            <td><span class="badge">{{ __('ui.dashboard.service.'.$service) }}</span></td>
                            <td><span class="badge {{ $status }}">{{ __('ui.dashboard.status.'.$status) }}</span></td>
                            <td><bdi>{{ $case->updated_at }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@else
    <section class="card">
        <div class="empty">{{ __('panel.empty') }}</div>
    </section>
@endif
@if(in_array($panelKey, ['owner', 'tech_admin'], true))
    <p class="notice">{{ __('panel.roles.'.$panelKey.'.subtitle') }}</p>
@endif
@endsection
