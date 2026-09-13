@extends('dashboard.layout')
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ $data['open_drafts_count'] }}</div><div class="label">{{ __('ui.dashboard.open_drafts') }}</div></div>
    <div class="stat"><div class="num">{{ count($data['assigned_reviews']) }}</div><div class="label">{{ __('ui.dashboard.assigned_reviews') }}</div></div>
</section>

<div class="card">
    <h2>{{ __('ui.dashboard.credential') }}</h2>
    <p>
        <span class="badge {{ $data['credential_status'] }}">{{ __('ui.dashboard.credential_status.'.$data['credential_status']) }}</span>
        —
        @if($data['can_publish'])<span class="ok">{{ __('ui.dashboard.can_publish') }}</span>@else<span class="muted">{{ __('ui.dashboard.cannot_publish') }}</span>@endif
    </p>
</div>

<div class="card">
    <h2>{{ __('ui.dashboard.assigned_reviews') }}</h2>
    @if(count($data['assigned_reviews']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_reviews') }}</div>
    @else
    <table>
        <thead><tr><th scope="col">{{ __('ui.dashboard.col_id') }}</th><th scope="col">{{ __('ui.dashboard.col_case') }}</th><th scope="col">{{ __('ui.dashboard.col_status') }}</th><th scope="col">{{ __('ui.dashboard.col_published') }}</th><th scope="col">{{ __('ui.dashboard.col_updated') }}</th></tr></thead>
        <tbody>
        @foreach($data['assigned_reviews'] as $r)
            <tr>
                <td>{{ $r['id'] }}</td>
                <td>{{ $r['case_id'] }}</td>
                <td><span class="badge {{ $r['case_status'] ?? '' }}">{{ __('ui.dashboard.status.'.$r['case_status']) }}</span></td>
                <td>@if($r['is_published'])<span class="ok">{{ __('ui.dashboard.yes') }}</span>@else<span class="muted">{{ __('ui.dashboard.no') }}</span>@endif</td>
                <td>{{ $r['updated_at'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
