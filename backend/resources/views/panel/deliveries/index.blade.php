@extends('panel.layout')
@section('title', __('panel.deliveries.title'))
@section('heading', __('panel.deliveries.title'))
@section('actions')
    <a class="btn" href="{{ route('integrations.index', ['locale'=>$locale]) }}">{{ __('panel.nav.integrations') }}</a>
    <a class="btn" href="{{ route('panel.launch-readiness.index', ['locale'=>$locale]) }}">{{ __('panel.nav.launch_readiness') }}</a>
@endsection
@section('content')
<div class="delivery-page">
    <section class="delivery-hero">
        <div>
            <div class="delivery-eyebrow">{{ __('panel.deliveries.system_label') }}</div>
            <h1>{{ __('panel.deliveries.title') }}</h1>
            <p>{{ __('panel.deliveries.intro') }}</p>
        </div>
        <div class="delivery-hero-meta">
            <span class="delivery-live-dot"></span>
            <span>{{ __('panel.deliveries.updated') }} <bdi>{{ $updatedAt }}</bdi></span>
            <span class="delivery-zone">Asia/Tehran</span>
        </div>
    </section>

    @if($summary['failed'] > 0 || $summary['stuck'] > 0)
        <div class="delivery-alert" role="status">
            <span class="delivery-alert-icon" aria-hidden="true">!</span>
            <div><strong>{{ __('panel.deliveries.attention_title') }}</strong><p>{{ __('panel.deliveries.attention') }}</p></div>
        </div>
    @else
        <div class="delivery-health" role="status"><span class="delivery-health-dot"></span>{{ __('panel.deliveries.healthy') }}</div>
    @endif

    <section class="delivery-metrics" aria-label="{{ __('panel.deliveries.overview') }}">
        <article class="delivery-metric metric-primary"><span>{{ __('panel.deliveries.last_24h') }}</span><strong>{{ number_format($summary['last_24h']) }}</strong><small>{{ __('panel.deliveries.total_activity') }}</small></article>
        <article class="delivery-metric"><span>{{ __('panel.deliveries.pending_outbox') }}</span><strong>{{ number_format($summary['pending_outbox']) }}</strong><small>{{ __('panel.deliveries.awaiting_processing') }}</small></article>
        <article class="delivery-metric"><span>{{ __('panel.deliveries.status.delivered') }}</span><strong>{{ number_format($summary['delivered']) }}</strong><small>{{ __('panel.deliveries.all_time') }}</small></article>
        <article class="delivery-metric metric-warning"><span>{{ __('panel.deliveries.status.failed') }}</span><strong>{{ number_format($summary['failed']) }}</strong><small>{{ __('panel.deliveries.all_time') }}</small></article>
        <article class="delivery-metric {{ $summary['stuck'] > 0 ? 'metric-danger' : '' }}"><span>{{ __('panel.deliveries.stuck') }}</span><strong>{{ number_format($summary['stuck']) }}</strong><small>{{ __('panel.deliveries.stuck_hint') }}</small></article>
    </section>

    <section class="delivery-filter-card">
        <div class="delivery-section-heading"><div><h2>{{ __('panel.deliveries.filter_title') }}</h2><p>{{ __('panel.deliveries.filter_hint') }}</p></div></div>
        <form class="delivery-filters" method="get" role="search">
            <div class="field"><label for="delivery-q">{{ __('panel.deliveries.search') }}</label><input id="delivery-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('panel.deliveries.search_placeholder') }}" autocomplete="off"></div>
            <div class="field"><label for="delivery-from">{{ __('panel.deliveries.from') }}</label><input id="delivery-from" type="{{ $locale === 'fa' ? 'text' : 'date' }}" name="from" value="{{ $filters['from'] }}" dir="ltr" inputmode="numeric" placeholder="{{ $locale === 'fa' ? '۱۴۰۵-۰۱-۰۱' : '' }}"></div>
            <div class="field"><label for="delivery-to">{{ __('panel.deliveries.to') }}</label><input id="delivery-to" type="{{ $locale === 'fa' ? 'text' : 'date' }}" name="to" value="{{ $filters['to'] }}" dir="ltr" inputmode="numeric" placeholder="{{ $locale === 'fa' ? '۱۴۰۵-۰۱-۳۱' : '' }}"></div>
            <div class="field"><label for="delivery-status">{{ __('panel.table.status') }}</label><select id="delivery-status" name="status"><option value="">{{ __('panel.deliveries.all_statuses') }}</option>@foreach(['sending','sent','delivered','failed'] as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __('panel.deliveries.status.'.$status) }}</option>@endforeach</select></div>
            <div class="field"><label for="delivery-locale">{{ __('panel.deliveries.locale') }}</label><select id="delivery-locale" name="locale"><option value="">{{ __('panel.deliveries.all_locales') }}</option>@foreach(['fa','ar','en'] as $deliveryLocale)<option value="{{ $deliveryLocale }}" @selected($filters['locale'] === $deliveryLocale)>{{ strtoupper($deliveryLocale) }}</option>@endforeach</select></div>
            <div class="field"><label for="delivery-channel">{{ __('panel.deliveries.channel') }}</label><select id="delivery-channel" name="channel"><option value="">{{ __('panel.deliveries.all_channels') }}</option><option value="sms" @selected($filters['channel'] === 'sms')>SMS</option></select></div>
            <div class="delivery-filter-actions"><button class="btn primary" type="submit">{{ __('panel.filter_apply') }}</button><a class="btn" href="{{ route('panel.deliveries.index', ['locale'=>$locale]) }}">{{ __('panel.deliveries.reset') }}</a></div>
        </form><p class="delivery-calendar-note">{{ __('panel.deliveries.calendar_note') }} <a href="https://www.time.ir/" target="_blank" rel="noopener noreferrer">time.ir</a></p>
    </section>

    <section class="delivery-history card">
        <div class="delivery-history-head"><div><h2>{{ __('panel.deliveries.history') }}</h2><p>{{ __('panel.deliveries.history_hint') }}</p></div><div class="delivery-history-tools"><span class="delivery-count">{{ number_format($deliveries->total()) }} {{ __('panel.deliveries.records') }}</span><a class="btn delivery-export" href="{{ request()->fullUrlWithQuery(['export'=>'csv']) }}">{{ __('panel.deliveries.export_csv') }}</a><small class="delivery-export-note">{{ __('panel.deliveries.export_limit') }}</small></div></div>
        @if($deliveries->isEmpty())
            <div class="delivery-empty"><span aria-hidden="true">⌕</span><strong>{{ __('panel.deliveries.empty_title') }}</strong><p>{{ __('panel.deliveries.empty') }}</p>@if(collect($filters)->filter()->isNotEmpty())<a class="btn" href="{{ route('panel.deliveries.index', ['locale'=>$locale]) }}">{{ __('panel.deliveries.reset') }}</a>@endif</div>
        @else
            <div class="table-wrap delivery-table-wrap"><table class="delivery-table"><thead><tr><th>{{ __('panel.deliveries.created') }} <small>Asia/Tehran</small></th><th>{{ __('panel.deliveries.event') }}</th><th>{{ __('panel.deliveries.template') }}</th><th>{{ __('panel.deliveries.channel') }}</th><th>{{ __('panel.deliveries.locale') }}</th><th>{{ __('panel.table.status') }}</th><th>{{ __('panel.deliveries.attempts') }}</th><th>{{ __('panel.deliveries.failure') }}</th></tr></thead><tbody>@foreach($deliveries as $row)<tr class="{{ $row->status === 'failed' ? 'row-attention' : '' }}"><td><bdi>{{ $row->display_created_at }}</bdi></td><td><code>{{ $row->event_type ?: '—' }}</code></td><td><code>{{ $row->template_key ?: '—' }}</code></td><td>{{ strtoupper($row->channel) }}</td><td>{{ strtoupper($row->recipient_locale) }}</td><td><span class="badge {{ $row->status }}">{{ __('panel.deliveries.status.'.$row->status) }}</span></td><td><bdi>{{ (int) ($row->attempts ?? 0) }}</bdi></td><td>{{ $row->failure_code ?: '—' }}</td></tr>@endforeach</tbody></table></div>
            <div class="card-body delivery-pagination">{{ $deliveries->links() }}</div>
        @endif
    </section>
    <p class="delivery-privacy"><span aria-hidden="true">◆</span>{{ __('panel.deliveries.privacy_note') }}</p>
</div>
@endsection
