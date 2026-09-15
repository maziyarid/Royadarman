@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['case_queue']) }}</div><div class="label">{{ __('ui.dashboard.case_queue') }}</div></div>
    <div class="stat"><div class="num">{{ $data['awaiting_patient_count'] }}</div><div class="label">{{ __('ui.dashboard.awaiting_patient') }}</div></div>
    <div class="stat"><div class="num">{{ $data['open_unassigned_support'] }}</div><div class="label">{{ __('ui.dashboard.open_support') }}</div></div>
    <div class="stat"><div class="num">{{ $data['home_service_pending'] }}</div><div class="label">{{ __('ui.dashboard.home_pending') }}</div></div>
</section>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.case_queue') }}</div>
    @if(count($data['case_queue']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_cases_queue') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('panel.table.reference') }}</th>
                        <th scope="col">{{ __('ui.dashboard.col_status') }}</th>
                        <th scope="col">{{ __('ui.dashboard.col_service') }}</th>
                        <th scope="col">{{ __('ui.dashboard.col_documents') }}</th>
                        <th scope="col">{{ __('ui.dashboard.col_review') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['case_queue'] as $c)
                        <tr>
                            <td><a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $c['id']]) }}"><bdi>{{ $c['public_reference'] }}</bdi></a></td>
                            <td><span class="badge {{ $c['status'] }}">{{ __('ui.dashboard.status.'.$c['status']) }}</span></td>
                            <td>{{ __('ui.dashboard.service.'.$c['service_type']) }}</td>
                            <td>{{ $c['documents_count'] }}</td>
                            <td>@if($c['has_published_review'])<span class="ok">{{ __('ui.dashboard.yes') }}</span>@else<span class="muted">{{ __('ui.dashboard.no') }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
