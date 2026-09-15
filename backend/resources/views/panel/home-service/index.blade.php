@extends('panel.layout')
@section('title', __('panel.nav.home_service'))
@section('heading', __('panel.nav.home_service'))
@section('content')
<p class="notice">{{ __('panel.home.tehran_only') }}</p>
<form class="filters" method="get">
    <div class="field">
        <label for="status">{{ __('panel.table.status') }}</label>
        <select id="status" name="status">
            <option value="">{{ __('panel.support.all_status') }}</option>
            @foreach(['requested','area_verified','coordinator_review','provider_requested','provider_accepted','patient_confirmed','scheduled','completed','cancelled','rejected','unable_to_service'] as $st)
                <option value="{{ $st }}" @selected($filterStatus === $st)>{{ __('ui.dashboard.home_status.'.$st) }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn" type="submit">{{ __('panel.filter_apply') }}</button>
</form>
<section class="card">
    <div class="card-head"><span>{{ __('panel.nav.home_service') }}</span><span class="muted">{{ $requests->total() }}</span></div>
    @if($requests->isEmpty())
        <div class="empty">{{ __('ui.dashboard.no_home_service') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('panel.table.reference') }}</th>
                        <th scope="col">{{ __('ui.dashboard.col_area') }}</th>
                        <th scope="col">{{ __('panel.table.status') }}</th>
                        <th scope="col">{{ __('panel.table.updated') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $row)
                        @php $status = $row->status instanceof \BackedEnum ? $row->status->value : $row->status; @endphp
                        <tr>
                            <td>
                                <a class="case-link" href="{{ route('panel.home-service.show', ['locale' => $locale, 'homeService' => $row->id]) }}">
                                    <bdi>{{ $row->case?->public_reference ?? $row->id }}</bdi>
                                </a>
                            </td>
                            <td>{{ __('request.areas.'.$row->tehran_area) }}</td>
                            <td><span class="badge {{ $status }}">{{ __('ui.dashboard.home_status.'.$status) }}</span></td>
                            <td><bdi>{{ $row->updated_at }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $requests->links() }}</div>
    @endif
</section>
@endsection
