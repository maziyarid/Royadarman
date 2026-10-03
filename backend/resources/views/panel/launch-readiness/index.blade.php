@extends('panel.layout')
@section('title', __('panel.launch.title'))
@section('heading', __('panel.launch.title'))
@push('scripts')
    <link rel="stylesheet" href="/assets/launch-readiness.css?v=20261003">
@endpush

@section('content')
@php
    $gates = $gates ?? [];
    $gateTotal = count($gates);
    $gatePassed = collect($gates)->filter(fn ($gate) => ($gate['ok'] ?? null) === true)->count();
    $health = $operationalHealth ?? ['state' => 'unknown', 'signals' => [], 'reasons' => []];
    $healthState = in_array($health['state'] ?? null, ['ok', 'degraded', 'unknown'], true) ? $health['state'] : 'unknown';
    $heartbeat = $runtimeHeartbeat ?? [];
    $heartbeatState = in_array($heartbeat['state'] ?? null, ['ok', 'degraded', 'unknown'], true) ? $heartbeat['state'] : 'unknown';
    $components = ['scheduler' => $heartbeat['scheduler'] ?? []];
    foreach (['otp', 'scanning', 'notifications', 'maintenance'] as $queue) {
        $components[$queue] = $heartbeat['queues'][$queue] ?? [];
    }
    $signalKeys = ['queued_jobs', 'failed_jobs', 'oldest_queued_age_seconds', 'stale_reserved_jobs', 'pending_outbox', 'stuck_outbox', 'stuck_sending_deliveries', 'failed_deliveries_24h', 'failed_deliveries_unresolved'];
    $manualKeys = ['legal_approved', 'sms_delivery_confirmed', 'scanner_drill_passed', 'backup_restore_rehearsed'];
    $isOwner = auth()->user()?->role === \App\Domain\Identity\Enums\UserRole::Owner;
    $publishedPolicies = 0;
    $policyCells = count($policyCoverage ?? []) * 3;
    foreach (($policyCoverage ?? []) as $coverage) {
        foreach (['fa', 'ar', 'en'] as $policyLocale) {
            $publishedPolicies += ($coverage[$policyLocale] ?? null) === true ? 1 : 0;
        }
    }
    $displayDate = static function ($value): ?array {
        if (!$value instanceof \DateTimeInterface && (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}[T ]/', $value))) {
            return null;
        }
        try {
            $date = \Illuminate\Support\Carbon::parse($value);
            return ['iso' => $date->toIso8601String(), 'display' => $date->timezone('Asia/Tehran')->format('Y-m-d H:i:s')];
        } catch (\Throwable) {
            return null;
        }
    };
    $observedAt = $displayDate($heartbeat['observed_at'] ?? null);
    $builtAt = $displayDate($release['built_at'] ?? null);
    $gateSectionLinks = ['policies' => 'launch-policies', 'sms_delivery' => 'launch-manual', 'scanner_drill' => 'launch-manual', 'legal_approval' => 'launch-manual', 'backup_restore' => 'launch-manual', 'operational_backlog' => 'launch-operations', 'runtime_heartbeat' => 'launch-runtime'];
@endphp
<div class="launch-readiness-workspace">
    <section class="hero-panel launch-overview" aria-labelledby="launch-overview-title">
        <div class="launch-overview-heading">
            <div><h2 id="launch-overview-title">{{ __('panel.launch.title') }}</h2><p>{{ __('panel.launch.intro') }}</p></div>
            <div class="launch-status {{ $ready ? 'ready' : 'blocked' }}">
                <strong>{{ $ready ? __('panel.launch.ready') : __('panel.launch.blocked') }}</strong>
                <span>{{ $intakeEnabled ? __('panel.launch.intake_on') : __('panel.launch.intake_off') }}</span>
            </div>
        </div>
        <p class="launch-completion" id="launch-completion-label">{{ __('launch.gate_completion', ['passed' => $gatePassed, 'total' => $gateTotal]) }}</p>
        @if($gateTotal > 0)<progress class="launch-progress" value="{{ $gatePassed }}" max="{{ $gateTotal }}" aria-labelledby="launch-completion-label"></progress>@endif
        <nav class="launch-section-links" aria-label="{{ __('launch.sections') }}">
            <a href="#launch-gates">{{ __('launch.gates_title') }}</a><a href="#launch-runtime">{{ __('launch.heartbeat_title') }}</a>
            <a href="#launch-operations">{{ __('panel.launch.runtime_title') }}</a><a href="#launch-manual">{{ __('panel.launch.manual_title') }}</a>
            <a href="#launch-policies">{{ __('panel.launch.policy_title') }}</a>
        </nav>
    </section>

    @if($intakeEnabled && !$ready)
        <div class="notice danger" role="alert">{{ __('panel.launch.intake_unsafe_notice') }}</div>
    @elseif(!$intakeEnabled)
        <div class="notice">{{ __('panel.launch.intake_safe_notice') }}</div>
    @endif
    @if($errors->any())
        <section class="notice error launch-validation-errors" id="launch-validation-errors" role="alert" tabindex="-1">
            <h2>{{ __('launch.validation_title') }}</h2><p>{{ __('launch.validation_help') }}</p>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            <a href="#launch-manual">{{ __('panel.launch.manual_title') }}</a>
        </section>
    @endif

    <section class="launch-section" id="launch-gates" aria-labelledby="launch-gates-title">
        <h2 id="launch-gates-title">{{ __('launch.gates_title') }}</h2>
        <div class="launch-grid">
            @foreach($gates as $key => $gate)
                @php
                    $knownGate = is_bool($gate['ok'] ?? null);
                    $gateState = !$knownGate ? 'unknown' : ($gate['ok'] ? 'ok' : 'blocked');
                    $titleKey = $key === 'runtime_heartbeat' ? 'runtime.gate_title' : 'panel.launch.gates.'.$key;
                    $helpKey = $key === 'runtime_heartbeat' ? 'runtime.gate_help' : 'panel.launch.gate_help.'.$key;
                @endphp
                <article class="launch-gate {{ $gateState }}">
                    <div class="launch-gate-head"><span class="status-dot" aria-hidden="true"></span>
                        <h3>{{ \Illuminate\Support\Facades\Lang::has($titleKey) ? __($titleKey) : __('launch.'.($key === 'runtime_heartbeat' ? 'runtime_gate_title' : 'unknown_gate')) }}</h3>
                    </div>
                    <p>{{ \Illuminate\Support\Facades\Lang::has($helpKey) ? __($helpKey) : __('launch.'.($key === 'runtime_heartbeat' ? 'runtime_caveat' : 'unknown_gate_help')) }}</p>
                    @if($key === 'operational_backlog')
                        @if(!empty($health['reasons']))
                            <ul class="launch-reasons">
                                @foreach($health['reasons'] as $reason)
                                    <li>{{ \Illuminate\Support\Facades\Lang::has('launch.health_reasons.'.$reason) ? __('launch.health_reasons.'.$reason) : __('launch.unknown_reason') }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @elseif(isset($gate['detail']) && is_scalar($gate['detail']) && (string) $gate['detail'] !== '')
                        <div class="launch-detail"><bdi>{{ $gate['detail'] }}</bdi></div>
                    @endif
                    <div class="launch-result">{{ !$knownGate ? __('launch.unknown') : ($gate['ok'] ? __('panel.launch.pass') : __('panel.launch.pending')) }}</div>
                    @if(isset($gateSectionLinks[$key]))<a class="launch-detail-link" href="#{{ $gateSectionLinks[$key] }}">{{ __('launch.view_evidence') }}</a>@endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="card pad launch-section" id="launch-runtime" aria-labelledby="launch-runtime-title">
        <div class="launch-section-heading"><h2 id="launch-runtime-title">{{ __('launch.heartbeat_title') }}</h2><span class="launch-state {{ $heartbeatState }}">{{ __('launch.heartbeat_states.'.$heartbeatState) }}</span></div>
        <p class="muted">{{ __('launch.heartbeat_intro') }}</p>
        <div class="table-wrap">
            <table class="launch-evidence-table">
                <caption>{{ __('launch.heartbeat_caption') }}</caption>
                <thead><tr><th scope="col">{{ __('launch.component') }}</th><th scope="col">{{ __('launch.observation') }}</th><th scope="col">{{ __('launch.probe_age') }}</th></tr></thead>
                <tbody>
                    @foreach($components as $component => $evidence)
                        @php
                            $evidenceState = in_array($evidence['state'] ?? null, ['recent', 'stale', 'unobservable'], true) ? $evidence['state'] : 'unobservable';
                            $age = isset($evidence['age_seconds']) && is_int($evidence['age_seconds']) && $evidence['age_seconds'] >= 0 ? $evidence['age_seconds'] : null;
                        @endphp
                        <tr data-runtime-component="{{ $component }}" data-runtime-state="{{ $evidenceState }}">
                            <th scope="row">{{ __('launch.components.'.$component) }}</th>
                            <td><span class="launch-state {{ $evidenceState }}">{{ __('launch.evidence_states.'.$evidenceState) }}</span></td>
                            <td>{{ $age === null ? __('launch.unknown') : __('launch.seconds', ['count' => number_format($age)]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="hint">{{ __('launch.observed_at') }} @if($observedAt)<time datetime="{{ $observedAt['iso'] }}"><bdi>{{ $observedAt['display'] }}</bdi></time>@else{{ __('launch.unknown') }}@endif</p>
        <p class="notice">{{ __('launch.runtime_caveat') }}</p>
        @if($heartbeatState === 'unknown')<p class="notice">{{ __('panel.launch.worker_unobservable') }}</p>@endif
        <p class="hint">{{ __('launch.timezone_hint') }}</p>
    </section>

    <section class="card pad launch-section" id="launch-operations" aria-labelledby="launch-operations-title">
        <div class="launch-section-heading"><h2 id="launch-operations-title">{{ __('panel.launch.runtime_title') }}</h2><span class="launch-state {{ $healthState }}">{{ __('launch.health_states.'.$healthState) }}</span></div>
        <p class="muted">{{ __('launch.health_intro') }}</p>
        <dl class="launch-signal-grid">
            @foreach($signalKeys as $signal)
                @php $signalValue = $health['signals'][$signal] ?? null; @endphp
                <div class="launch-signal" data-health-signal="{{ $signal }}"><dt>{{ __('launch.signals.'.$signal) }}</dt><dd>@if(is_int($signalValue) && $signalValue >= 0){{ $signal === 'oldest_queued_age_seconds' ? __('launch.seconds', ['count' => number_format($signalValue)]) : number_format($signalValue) }}@else{{ __('launch.unknown') }}@endif</dd></div>
            @endforeach
        </dl>
        <div class="launch-release">
            <h3>{{ __('panel.launch.release') }}</h3>
            <dl class="launch-release-facts">
                <div><dt>{{ __('panel.launch.release') }}</dt><dd><bdi>{{ isset($release['commit']) && is_string($release['commit']) && trim($release['commit']) !== '' ? $release['commit'] : __('launch.unknown') }}</bdi></dd></div>
                <div><dt>{{ __('panel.launch.built_at') }}</dt><dd>@if($builtAt)<time datetime="{{ $builtAt['iso'] }}"><bdi>{{ $builtAt['display'] }}</bdi></time>@else{{ __('launch.unknown') }}@endif</dd></div>
            </dl>
            <p class="hint">{{ __('launch.timezone_hint') }}</p>
        </div>
    </section>

    <section class="card pad launch-section" id="launch-manual" aria-labelledby="launch-manual-title" tabindex="-1">
        <h2 id="launch-manual-title">{{ __('panel.launch.manual_title') }}</h2>
        <p class="muted">{{ __('panel.launch.manual_intro') }}</p>
        @if($isOwner)<p class="notice">{{ __('panel.security.reauthenticate') }} {{ __('launch.reauthentication_help') }}</p>@else<p class="notice">{{ __('launch.owner_only') }}</p>@endif
        <div class="manual-gates">
            @foreach($manualKeys as $gate)
                @php
                    $state = $manual[$gate] ?? ['confirmed' => null, 'confirmed_at' => null];
                    $confirmationKnown = is_bool($state['confirmed'] ?? null);
                    $confirmedAt = $displayDate($state['confirmed_at'] ?? null);
                @endphp
                <article class="manual-gate">
                    <div><h3>{{ __('panel.launch.manual.'.$gate) }}</h3><p class="muted">{{ __('panel.launch.manual_help.'.$gate) }}</p>
                        <span class="launch-state {{ !$confirmationKnown ? 'unknown' : ($state['confirmed'] ? 'ok' : 'blocked') }}">{{ !$confirmationKnown ? __('launch.unknown') : ($state['confirmed'] ? __('launch.confirmed') : __('launch.not_confirmed')) }}</span>
                        @if($confirmedAt)<p class="launch-ack-date">{{ __('panel.launch.confirmed_at') }} <time datetime="{{ $confirmedAt['iso'] }}"><bdi>{{ $confirmedAt['display'] }}</bdi></time></p>@endif
                    </div>
                    @if($isOwner && $confirmationKnown)
                        <form method="post" action="{{ route('panel.launch-readiness.acknowledge', ['locale'=>$locale]) }}" data-launch-acknowledgement data-confirm="{{ __($state['confirmed'] ? 'launch.revoke_acknowledgement' : 'launch.confirm_acknowledgement', ['item' => __('panel.launch.manual.'.$gate)]) }}">
                            @csrf
                            <input type="hidden" name="gate" value="{{ $gate }}">
                            <input type="hidden" name="confirmed" value="{{ $state['confirmed'] ? '0' : '1' }}">
                            <button class="btn {{ $state['confirmed'] ? '' : 'primary' }}" type="submit">{{ $state['confirmed'] ? __('panel.launch.revoke_confirmation') : __('panel.launch.confirm') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
        <p class="hint">{{ __('launch.timezone_hint') }}</p>
    </section>

    <section class="card pad launch-section" id="launch-policies" aria-labelledby="launch-policies-title">
        <h2 id="launch-policies-title">{{ __('panel.launch.policy_title') }}</h2><p class="muted">{{ __('panel.launch.policy_intro') }}</p>
        <p class="launch-completion">{{ __('launch.policy_completion', ['published' => $publishedPolicies, 'total' => $policyCells]) }}</p>
        <div class="table-wrap"><table class="launch-evidence-table" aria-describedby="launch-policy-caption">
            <caption id="launch-policy-caption">{{ __('launch.policy_caption') }}</caption>
            <thead><tr><th scope="col">{{ __('panel.launch.policy') }}</th><th scope="col">فارسی (FA)</th><th scope="col">العربية (AR)</th><th scope="col">English (EN)</th></tr></thead>
            <tbody>
                @foreach(($policyCoverage ?? []) as $policy => $locales)
                    <tr><th scope="row">{{ \Illuminate\Support\Facades\Lang::has('panel.policies.keys.'.$policy) ? __('panel.policies.keys.'.$policy) : __('panel.launch.policy') }}<small class="launch-policy-key"><code>{{ $policy }}</code></small></th>
                        @foreach(['fa','ar','en'] as $policyLocale)
                            @php $published = $locales[$policyLocale] ?? null; @endphp
                            <td><span class="gate-chip {{ !is_bool($published) ? 'unknown' : ($published ? 'ok' : 'blocked') }}">{{ !is_bool($published) ? __('launch.unknown') : ($published ? __('panel.launch.published') : __('panel.launch.missing')) }}</span></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>

    <section class="card pad launch-section" aria-labelledby="launch-next-title">
        <h2 id="launch-next-title">{{ __('panel.launch.next_step') }}</h2>
        @if($ready && !$intakeEnabled)<p>{{ __('panel.launch.ready_next') }}</p>@elseif($ready && $intakeEnabled)<p>{{ __('panel.launch.live_next') }}</p>@else<p>{{ __('panel.launch.blocked_next') }}</p>@endif
        <p class="hint">{{ __('panel.launch.no_auto_enable') }}</p>
    </section>
</div>
@endsection
