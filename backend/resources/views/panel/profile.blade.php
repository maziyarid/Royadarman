@push('scripts')
    @vite(['resources/js/passkeys.js'])
@endpush
@extends('panel.layout')
@section('title', __('panel.nav.profile'))
@section('heading', __('panel.nav.profile'))
@section('content')
<section class="card pad">
    <div class="facts">
        <div class="fact"><small>{{ __('panel.profile.role') }}</small>{{ __('ui.dashboard.roles.'.$panelKey) }}</div>
        <div class="fact"><small>{{ __('panel.profile.locale') }}</small>{{ strtoupper($profileUser->locale) }}</div>
        @if($profileUser->name)
            <div class="fact"><small>{{ __('panel.profile.name') }}</small>{{ $profileUser->name }}</div>
        @endif
    </div>
</section>
@if($isDemo)
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@else
    <section class="card pad">
        <h2>{{ __('panel.profile.preferences') }}</h2>
        <form method="post" action="{{ route('panel.profile.update', ['locale' => $locale]) }}">
            @csrf
            @method('PATCH')
            <div class="field">
                <label for="name">{{ __('panel.profile.name') }}</label>
                <input id="name" name="name" maxlength="80" value="{{ old('name', $profileUser->name) }}">
            </div>
            <div class="field">
                <label for="pref-locale">{{ __('panel.profile.locale') }}</label>
                <select id="pref-locale" name="locale" required>
                    @foreach(['fa' => 'فارسی', 'ar' => 'العربية', 'en' => 'English'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $profileUser->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn primary" type="submit">{{ __('panel.profile.save') }}</button>
        </form>
    </section>

    @if(filled($profileUser->phone_hash))
        <section class="card pad" style="margin-top:16px">
            <h2>{{ __('panel.credentials.title') }}</h2>
            <p class="muted">{{ __('panel.credentials.intro') }}</p>
            @if($credentialsConfigured)
                <p class="notice success">{{ __('panel.credentials.configured', ['username' => $profileUser->username]) }}</p>
            @else
                <p class="notice">{{ __('panel.credentials.setup_hint') }}</p>
            @endif
            <form method="post" action="{{ route('panel.profile.credentials', ['locale' => $locale]) }}">
                @csrf
                <div class="field">
                    <label for="credential-username">{{ __('panel.credentials.username') }}</label>
                    <input id="credential-username" name="username" dir="ltr" autocomplete="username" minlength="3" maxlength="32" value="{{ old('username', $profileUser->username) }}" required pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,31}">
                </div>
                @if($credentialsConfigured && session('auth_method') !== 'otp')
                    <div class="field">
                        <label for="credential-current-password">{{ __('panel.credentials.current_password') }}</label>
                        <input id="credential-current-password" name="current_password" type="password" dir="ltr" autocomplete="current-password" required maxlength="128">
                    </div>
                @endif
                <div class="form-grid">
                    <div class="field">
                        <label for="credential-password">{{ __('panel.credentials.new_password') }}</label>
                        <input id="credential-password" name="password" type="password" dir="ltr" autocomplete="new-password" minlength="12" maxlength="128" required>
                    </div>
                    <div class="field">
                        <label for="credential-password-confirmation">{{ __('panel.credentials.confirm_password') }}</label>
                        <input id="credential-password-confirmation" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password" minlength="12" maxlength="128" required>
                    </div>
                </div>
                <p class="hint">{{ __('panel.credentials.password_hint') }}</p>
                <button class="btn primary" type="submit">{{ __('panel.credentials.save') }}</button>
            </form>
        </section>
    @endif

    @if($profileUser->role->isStaff())
        <section class="card pad" style="margin-top:16px">
            <h2>{{ __('panel.security.title') }}</h2>
            <p class="muted">{{ __('panel.security.intro') }}</p>
            <p class="notice success">{{ __('panel.security.otp_primary') }}</p>

            <h3>{{ __('panel.security.totp_title') }}</h3>
            <p class="muted">{{ $mfaConfigured ? __('panel.security.totp_on') : __('panel.security.totp_off') }}</p>

            @if(!empty($newRecoveryCodes))
                <div class="notice" role="status">
                    <strong>{{ __('panel.security.recovery_title') }}</strong>
                    <p>{{ __('panel.security.recovery_notice') }}</p>
                    <div dir="ltr" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;font-family:monospace">
                        @foreach($newRecoveryCodes as $code)
                            <code>{{ $code }}</code>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!$mfaConfigured && empty($pendingTotpSecret))
                <form method="post" action="{{ route('panel.profile.security.totp.start', ['locale'=>$locale]) }}">
                    @csrf
                    <button class="btn primary" type="submit">{{ __('panel.security.enable_totp') }}</button>
                </form>
            @elseif(!$mfaConfigured && !empty($pendingTotpSecret))
                <div class="notice">
                    <strong>{{ __('panel.security.setup_title') }}</strong>
                    <p>{{ __('panel.security.setup_help') }}</p>
                    <div class="field">
                        <label>{{ __('panel.security.manual_key') }}</label>
                        <input value="{{ $pendingTotpSecret }}" readonly dir="ltr" onclick="this.select()">
                    </div>
                    <div class="field">
                        <label>{{ __('panel.security.authenticator_uri') }}</label>
                        <input value="{{ $pendingTotpUri }}" readonly dir="ltr" onclick="this.select()">
                    </div>
                </div>
                <form method="post" action="{{ route('panel.profile.security.totp.confirm', ['locale'=>$locale]) }}">
                    @csrf
                    <div class="field">
                        <label for="totp-confirm">{{ __('panel.security.confirm_code') }}</label>
                        <input id="totp-confirm" name="totp_code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" minlength="6" required>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        <button class="btn primary" type="submit">{{ __('panel.security.confirm') }}</button>
                    </div>
                </form>
                <form method="post" action="{{ route('panel.profile.security.totp.cancel', ['locale'=>$locale]) }}" style="margin-top:8px">
                    @csrf
                    <button class="btn" type="submit">{{ __('panel.security.cancel') }}</button>
                </form>
            @else
                <details>
                    <summary>{{ __('panel.security.disable') }}</summary>
                    <p class="muted">{{ __('panel.security.disable_help') }}</p>
                    <form method="post" action="{{ route('panel.profile.security.totp.disable', ['locale'=>$locale]) }}">
                        @csrf
                        <div class="form-grid">
                            <div class="field">
                                <label for="totp-disable">{{ __('panel.security.confirm_code') }}</label>
                                <input id="totp-disable" name="totp_code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6">
                            </div>
                            <div class="field">
                                <label for="recovery-disable">{{ __('panel.security.recovery_code') }}</label>
                                <input id="recovery-disable" name="recovery_code" dir="ltr" autocomplete="off">
                            </div>
                        </div>
                        <button class="btn danger" type="submit">{{ __('panel.security.disable') }}</button>
                    </form>
                </details>
            @endif

            <hr style="margin:24px 0;border:0;border-top:1px solid var(--line)">
            <h3>{{ __('panel.security.passkey_title') }}</h3>
            <p class="muted">{{ __('panel.security.passkey_intro') }}</p>
            <div
                data-passkey-management
                data-working="{{ __('panel.security.passkey_working') }}"
                data-registered="{{ __('panel.security.passkey_registered') }}"
                data-error="{{ __('panel.security.passkey_error') }}"
                data-unsupported="{{ __('panel.security.passkey_unsupported') }}"
                data-name-required="{{ __('panel.security.passkey_name_required') }}"
            >
                <div class="notice" data-passkey-message hidden></div>
                <div class="form-grid">
                    <div class="field">
                        <label for="passkey-name">{{ __('panel.security.passkey_name') }}</label>
                        <input id="passkey-name" data-passkey-name maxlength="80" placeholder="{{ __('panel.security.passkey_name_placeholder') }}">
                    </div>
                </div>
                <button class="btn primary" type="button" data-passkey-register hidden>{{ __('panel.security.passkey_add') }}</button>

                @if($passkeys->isEmpty())
                    <p class="muted" style="margin-top:12px">{{ __('panel.security.passkey_none') }}</p>
                @else
                    <div class="task-grid compact" style="padding:0;margin-top:14px">
                        @foreach($passkeys as $passkey)
                            <article class="task-card">
                                <div class="task-card-main">
                                    <strong>{{ $passkey->name }}</strong>
                                    <span class="task-meta">
                                        {{ __('panel.security.passkey_created') }} {{ optional($passkey->created_at)->format('Y-m-d H:i') }}
                                        @if($passkey->last_used_at)
                                            · {{ __('panel.security.passkey_last_used') }} {{ $passkey->last_used_at->format('Y-m-d H:i') }}
                                        @endif
                                    </span>
                                </div>
                                <form method="post" action="{{ route('passkey.destroy', ['passkey'=>$passkey->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit">{{ __('panel.security.passkey_delete') }}</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
                <p class="hint" style="margin-top:10px">{{ __('panel.security.passkey_reauth_hint') }}</p>
            </div>
        </section>
    @endif

    <section class="card pad" style="margin-top:16px">
        <h2>{{ __('panel.sessions.title') }}</h2>
        <p class="muted" style="margin-top:0">{{ __('panel.sessions.intro') }}</p>
        @if(($sessions ?? collect())->isEmpty())
            <p class="muted">{{ __('panel.sessions.empty') }}</p>
        @else
            <div class="task-grid compact" style="padding:0;margin:12px 0 0">
                @foreach($sessions as $row)
                    <div class="task-card">
                        <div class="task-card-main">
                            <strong>
                                {{ $row['device_label'] }}
                                @if($row['is_current'])
                                    <span class="sla-chip sla-ok">{{ __('panel.sessions.current') }}</span>
                                @endif
                            </strong>
                            <span class="task-meta">
                                {{ $row['ip_address'] ?? '—' }}
                                · {{ __('panel.sessions.last_active') }}:
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($row['last_activity'])->diffForHumans() }}
                            </span>
                        </div>
                        @if(! $row['is_current'])
                            <form method="post" action="{{ route('panel.profile.sessions.revoke', ['locale' => $locale, 'session' => $row['id']]) }}" onsubmit="return confirm(@json(__('panel.sessions.confirm_one')))">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger sm" type="submit">{{ __('panel.sessions.revoke') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:16px">
                <form method="post" action="{{ route('panel.profile.sessions.revoke_others', ['locale' => $locale]) }}" onsubmit="return confirm(@json(__('panel.sessions.confirm_others')))">
                    @csrf
                    <button class="btn" type="submit">{{ __('panel.sessions.revoke_others') }}</button>
                </form>
                <form method="post" action="{{ route('panel.profile.sessions.revoke_all', ['locale' => $locale]) }}" onsubmit="return confirm(@json(__('panel.sessions.confirm_all')))">
                    @csrf
                    <button class="btn danger" type="submit">{{ __('panel.sessions.revoke_all') }}</button>
                </form>
            </div>
        @endif
    </section>
@endif
@endsection
