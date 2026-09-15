@extends('panel.layout')
@section('title', __('ui.dashboard.title'))
@section('heading', __('ui.dashboard.title'))
@section('content')
<section class="stat-grid">
    <div class="stat"><div class="num">{{ count($data['clinics']) }}</div><div class="label">{{ __('ui.dashboard.my_clinics') }}</div></div>
    <div class="stat"><div class="num">{{ count($data['active_referral_grants']) }}</div><div class="label">{{ __('ui.dashboard.active_grants') }}</div></div>
    <div class="stat"><div class="num">{{ $data['pending_proposals'] }}</div><div class="label">{{ __('ui.dashboard.pending_proposals') }}</div></div>
</section>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.my_clinics') }}</div>
    @if(count($data['clinics']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_clinics') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('ui.dashboard.col_name') }}</th><th scope="col">{{ __('ui.dashboard.col_status') }}</th></tr></thead>
                <tbody>
                    @foreach($data['clinics'] as $c)
                        <tr>
                            <td>{{ $c['name'] }}</td>
                            <td>@if($c['is_active'])<span class="ok">{{ __('ui.dashboard.active') }}</span>@else<span class="muted">{{ __('ui.dashboard.inactive') }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<div class="card">
    <div class="card-head">{{ __('ui.dashboard.active_grants') }}</div>
    @if(count($data['active_referral_grants']) === 0)
        <div class="empty">{{ __('ui.dashboard.no_grants') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('panel.table.reference') }}</th><th scope="col">{{ __('ui.dashboard.col_status') }}</th><th scope="col">{{ __('ui.dashboard.col_expires') }}</th></tr></thead>
                <tbody>
                    @foreach($data['active_referral_grants'] as $g)
                        <tr>
                            <td>
                                @if(!empty($g['public_reference']))
                                    <a class="case-link" href="{{ route('panel.case', ['locale' => $locale, 'case' => $g['case_id']]) }}"><bdi>{{ $g['public_reference'] }}</bdi></a>
                                @else
                                    {{ $g['case_id'] }}
                                @endif
                            </td>
                            <td><span class="badge {{ $g['case_status'] }}">{{ __('ui.dashboard.status.'.$g['case_status']) }}</span></td>
                            <td><bdi>{{ $g['expires_at'] }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
