@extends('panel.layout')
@section('title', __('panel.launch.title'))
@section('heading', __('panel.launch.title'))

@section('content')
<section class="hero-panel">
    <div>
        <h1>{{ __('panel.launch.title') }}</h1>
        <p>{{ __('panel.launch.intro') }}</p>
    </div>
    <div class="launch-status {{ $ready ? 'ready' : 'blocked' }}">
        <strong>{{ $ready ? __('panel.launch.ready') : __('panel.launch.blocked') }}</strong>
        <span>{{ $intakeEnabled ? __('panel.launch.intake_on') : __('panel.launch.intake_off') }}</span>
    </div>
</section>

@if($intakeEnabled && !$ready)
    <div class="notice danger">{{ __('panel.launch.intake_unsafe_notice') }}</div>
@elseif(!$intakeEnabled)
    <div class="notice">{{ __('panel.launch.intake_safe_notice') }}</div>
@endif

<section class="launch-grid">
    @foreach($gates as $key => $gate)
        <article class="launch-gate {{ $gate['ok'] ? 'ok' : 'blocked' }}">
            <div class="launch-gate-head">
                <span class="status-dot" aria-hidden="true"></span>
                <strong>{{ __('panel.launch.gates.'.$key) }}</strong>
            </div>
            <p>{{ __('panel.launch.gate_help.'.$key) }}</p>
            @if($gate['detail'] !== null && $gate['detail'] !== '')
                <div class="launch-detail"><bdi>{{ $gate['detail'] }}</bdi></div>
            @endif
            <div class="launch-result">
                {{ $gate['ok'] ? __('panel.launch.pass') : __('panel.launch.pending') }}
            </div>
        </article>
    @endforeach
</section>

<section class="card pad">
    <div class="heading-row">
        <div>
            <h2>{{ __('panel.launch.manual_title') }}</h2>
            <p class="muted">{{ __('panel.launch.manual_intro') }}</p>
        </div>
    </div>

    <div class="manual-gates">
        @foreach(['legal_approved','sms_delivery_confirmed','scanner_drill_passed','backup_restore_rehearsed'] as $gate)
            @php $state = $manual[$gate]; @endphp
            <div class="manual-gate">
                <div>
                    <strong>{{ __('panel.launch.manual.'.$gate) }}</strong>
                    <p class="muted">{{ __('panel.launch.manual_help.'.$gate) }}</p>
                    @if($state['confirmed_at'])
                        <small class="muted">{{ __('panel.launch.confirmed_at') }} <bdi>{{ $state['confirmed_at'] }}</bdi></small>
                    @endif
                </div>
                @if(auth()->user()?->role === \App\Domain\Identity\Enums\UserRole::Owner)
                    <form method="post" action="{{ route('panel.launch-readiness.acknowledge', ['locale'=>$locale]) }}">
                        @csrf
                        <input type="hidden" name="gate" value="{{ $gate }}">
                        <input type="hidden" name="confirmed" value="{{ $state['confirmed'] ? '0' : '1' }}">
                        <button class="btn {{ $state['confirmed'] ? '' : 'primary' }}" type="submit">
                            {{ $state['confirmed'] ? __('panel.launch.revoke_confirmation') : __('panel.launch.confirm') }}
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</section>

<section class="card pad">
    <h2>{{ __('panel.launch.policy_title') }}</h2>
    <p class="muted">{{ __('panel.launch.policy_intro') }}</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{{ __('panel.launch.policy') }}</th>
                    <th>FA</th>
                    <th>AR</th>
                    <th>EN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($policyCoverage as $policy => $locales)
                    <tr>
                        <td><code>{{ $policy }}</code></td>
                        @foreach(['fa','ar','en'] as $policyLocale)
                            <td>
                                <span class="gate-chip {{ $locales[$policyLocale] ? 'ok' : 'blocked' }}">
                                    {{ $locales[$policyLocale] ? __('panel.launch.published') : __('panel.launch.missing') }}
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="card pad">
    <h2>{{ __('panel.launch.runtime_title') }}</h2>
    <div class="facts">
        <div class="fact"><small>{{ __('panel.launch.queued_jobs') }}</small>{{ $queuedJobs ?? '—' }}</div>
        <div class="fact"><small>{{ __('panel.launch.failed_jobs') }}</small>{{ $failedJobs ?? '—' }}</div>
        <div class="fact"><small>{{ __('panel.launch.release') }}</small><bdi>{{ $release['commit'] ?? '—' }}</bdi></div>
        <div class="fact"><small>{{ __('panel.launch.built_at') }}</small><bdi>{{ $release['built_at'] ?? '—' }}</bdi></div>
    </div>
    <p class="notice">{{ __('panel.launch.worker_unobservable') }}</p>
</section>

<section class="card pad">
    <h2>{{ __('panel.launch.next_step') }}</h2>
    @if($ready && !$intakeEnabled)
        <p>{{ __('panel.launch.ready_next') }}</p>
    @elseif($ready && $intakeEnabled)
        <p>{{ __('panel.launch.live_next') }}</p>
    @else
        <p>{{ __('panel.launch.blocked_next') }}</p>
    @endif
    <p class="hint">{{ __('panel.launch.no_auto_enable') }}</p>
</section>
@endsection
