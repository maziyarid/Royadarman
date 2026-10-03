@push('scripts')
    <link rel="stylesheet" href="/assets/profile-workspace.css?v=20261003">
    <script src="/assets/profile-workspace.js?v=20261003" defer></script>
    @vite(['resources/js/passkeys.js'])
@endpush
@extends('panel.layout')
@section('title', __('panel.nav.profile'))
@section('heading', __('panel.nav.profile'))
@section('content')
@php
    $selfProfile = $selfProfile ?? ['clinic_affiliations' => [], 'practitioner_credential' => null];
    $profileFieldIds = [
        'name' => 'name', 'locale' => 'pref-locale', 'username' => 'credential-username',
        'current_password' => 'credential-current-password', 'password' => 'credential-password',
        'password_confirmation' => 'credential-password-confirmation',
        'totp_code' => !empty($pendingTotpSecret) ? 'totp-confirm' : 'totp-disable',
        'recovery_code' => 'recovery-disable',
    ];
@endphp
<div class="profile-workspace" data-profile-workspace
    data-copy-success="{{ __('panel.profile_workspace.copy_success') }}"
    data-copy-fallback="{{ __('panel.profile_workspace.copy_fallback') }}"
    data-saving="{{ __('panel.profile_workspace.saving') }}">
<section class="card pad profile-hero">
    <div class="profile-heading">
        <span class="profile-avatar" aria-hidden="true">{{ mb_substr($profileUser->name ?: __('panel.nav.profile'), 0, 1) }}</span>
        <div><h2>{{ $profileUser->name ?: __('panel.nav.profile') }}</h2><p>{{ __('panel.profile_workspace.intro') }}</p></div>
    </div>
    <div class="facts">
        <div class="fact"><small>{{ __('panel.profile.role') }}</small>{{ __('ui.dashboard.roles.'.$panelKey) }}</div>
        <div class="fact"><small>{{ __('panel.profile.locale') }}</small>{{ strtoupper($profileUser->locale) }}</div>
        @if($profileUser->name)
            <div class="fact"><small>{{ __('panel.profile.name') }}</small>{{ $profileUser->name }}</div>
        @endif
    </div>
</section>
@if(!$isDemo)
    <nav class="profile-section-nav" aria-label="{{ __('panel.profile_workspace.sections') }}">
        <a href="#profile-preferences">{{ __('panel.profile.preferences') }}</a>
        <a href="#profile-affiliations">{{ __('panel.profile_workspace.affiliations') }}</a>
        @if(filled($profileUser->phone_hash))<a href="#profile-credentials">{{ __('panel.credentials.title') }}</a>@endif
        @if($profileUser->role->isStaff())<a href="#profile-security">{{ __('panel.security.title') }}</a>@endif
        <a href="#profile-sessions">{{ __('panel.sessions.title') }}</a>
    </nav>
    @if($errors->any())
        <section class="notice error profile-errors" id="profile-validation-errors" role="alert" tabindex="-1" data-profile-errors>
            <h2>{{ __('panel.profile_workspace.validation_title') }}</h2>
            <p>{{ __('panel.profile_workspace.validation_help') }}</p>
            <ul>
                @foreach($errors->messages() as $field => $messages)
                    @foreach($messages as $message)
                        <li>@if(isset($profileFieldIds[$field]))<a href="#{{ $profileFieldIds[$field] }}">{{ $message }}</a>@else{{ $message }}@endif</li>
                    @endforeach
                @endforeach
            </ul>
        </section>
    @endif
    <p class="notice" role="status" data-profile-action-status hidden></p>
@endif
@if($isDemo)
    <p class="notice">{{ __('panel.demo_mutations_disabled') }}</p>
@else
    <section class="card pad profile-section" id="profile-preferences" aria-labelledby="preferences-heading">
        <h2 id="preferences-heading">{{ __('panel.profile.preferences') }}</h2>
        <form method="post" data-profile-form action="{{ route('panel.profile.update', ['locale' => $locale]) }}">
            @csrf
            @method('PATCH')
            <div class="field">
                <label for="name">{{ __('panel.profile.name') }}</label>
                <input id="name" name="name" autocomplete="name" maxlength="80" value="{{ old('name', $profileUser->name) }}" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')<p class="profile-field-error" id="name-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="pref-locale">{{ __('panel.profile.locale') }}</label>
                <select id="pref-locale" name="locale" required @error('locale') aria-invalid="true" aria-describedby="pref-locale-error" @enderror>
                    @foreach(['fa' => 'فارسی', 'ar' => 'العربية', 'en' => 'English'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $profileUser->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('locale')<p class="profile-field-error" id="pref-locale-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn primary" type="submit">{{ __('panel.profile.save') }}</button>
        </form>
    </section>

    <section class="card pad profile-section" id="profile-affiliations" aria-labelledby="affiliations-heading">
        <h2 id="affiliations-heading">{{ __('panel.profile_workspace.affiliations') }}</h2>
        <p class="muted">{{ __('panel.profile_workspace.affiliation_privacy') }}</p>
        @forelse(($selfProfile['clinic_affiliations'] ?? []) as $affiliation)
            <article class="profile-affiliation">
                <div><h3>{{ $affiliation['clinic_name'] }}</h3><span class="badge">{{ __('panel.profile_workspace.membership_'.(in_array($affiliation['membership_role'], ['contact', 'reviewer'], true) ? $affiliation['membership_role'] : 'unknown')) }}</span></div>
                <dl class="profile-dates">
                    @foreach(['active_from' => 'affiliation_from', 'active_until' => 'affiliation_until'] as $dateKey => $labelKey)
                        <div><dt>{{ __('panel.profile_workspace.'.$labelKey) }}</dt><dd>
                            @if(!empty($affiliation[$dateKey]))
                                <time datetime="{{ $affiliation[$dateKey] }}"><bdi>{{ \Illuminate\Support\Carbon::parse($affiliation[$dateKey])->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</bdi></time>
                            @else
                                {{ __('panel.profile_workspace.'.($dateKey === 'active_until' ? 'no_expiry' : 'not_recorded')) }}
                            @endif
                        </dd></div>
                    @endforeach
                </dl>
            </article>
        @empty
            <p class="profile-empty">{{ __('panel.profile_workspace.affiliations_empty') }}</p>
        @endforelse
        @if($profileUser->role->value === 'clinician')
            @php($credential = $selfProfile['practitioner_credential'] ?? null)
            <div class="profile-credential">
                <h3>{{ __('panel.profile_workspace.practitioner_credential') }}</h3>
                @if($credential)
                    <p><span class="badge">{{ __('panel.profile_workspace.credential_'.(in_array($credential['credential_status'], ['pending', 'verified', 'suspended', 'expired', 'revoked'], true) ? $credential['credential_status'] : 'unknown')) }}</span></p>
                    <dl class="profile-dates">
                        @foreach(['verified_at' => 'verified_at', 'expires_at' => 'credential_expires_at'] as $dateKey => $labelKey)
                            <div><dt>{{ __('panel.profile_workspace.'.$labelKey) }}</dt><dd>
                                @if(!empty($credential[$dateKey]))
                                    <time datetime="{{ $credential[$dateKey] }}"><bdi>{{ \Illuminate\Support\Carbon::parse($credential[$dateKey])->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</bdi></time>
                                    @if($dateKey === 'expires_at' && \Illuminate\Support\Carbon::parse($credential[$dateKey])->isPast())
                                        <span class="badge">{{ __('panel.profile_workspace.expired') }}</span>
                                    @endif
                                @else
                                    {{ __('panel.profile_workspace.not_recorded') }}
                                @endif
                            </dd></div>
                        @endforeach
                    </dl>
                @else
                    <p class="profile-empty">{{ __('panel.profile_workspace.credential_empty') }}</p>
                @endif
            </div>
        @endif
        <p class="hint">{{ __('panel.profile_workspace.timezone_hint') }}</p>
    </section>

    @if(filled($profileUser->phone_hash))
        <section class="card pad profile-section" id="profile-credentials" aria-labelledby="credentials-heading">
            <h2 id="credentials-heading">{{ __('panel.credentials.title') }}</h2>
            <p class="muted">{{ __('panel.credentials.intro') }}</p>
            @if($credentialsConfigured)
                <p class="notice success">{{ __('panel.credentials.configured', ['username' => $profileUser->username]) }}</p>
            @else
                <p class="notice">{{ __('panel.credentials.setup_hint') }}</p>
            @endif
            <form method="post" data-profile-form action="{{ route('panel.profile.credentials', ['locale' => $locale]) }}">
                @csrf
                <div class="field">
                    <label for="credential-username">{{ __('panel.credentials.username') }}</label>
                    <input id="credential-username" name="username" dir="ltr" autocomplete="username" minlength="3" maxlength="32" value="{{ old('username', $profileUser->username) }}" required pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,31}" @error('username') aria-invalid="true" aria-describedby="credential-username-error" @enderror>
                    @error('username')<p class="profile-field-error" id="credential-username-error">{{ $message }}</p>@enderror
                </div>
                @if($credentialsConfigured && session('auth_method') !== 'otp')
                    <div class="field">
                        <label for="credential-current-password">{{ __('panel.credentials.current_password') }}</label>
                        <input id="credential-current-password" name="current_password" type="password" dir="ltr" autocomplete="current-password" required maxlength="128" @error('current_password') aria-invalid="true" aria-describedby="credential-current-password-error" @enderror>
                        @error('current_password')<p class="profile-field-error" id="credential-current-password-error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="form-grid">
                    <div class="field">
                        <label for="credential-password">{{ __('panel.credentials.new_password') }}</label>
                        <input id="credential-password" name="password" type="password" dir="ltr" autocomplete="new-password" minlength="12" maxlength="128" required @error('password') aria-invalid="true" aria-describedby="credential-password-error" @enderror>
                        @error('password')<p class="profile-field-error" id="credential-password-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label for="credential-password-confirmation">{{ __('panel.credentials.confirm_password') }}</label>
                        <input id="credential-password-confirmation" name="password_confirmation" type="password" dir="ltr" autocomplete="new-password" minlength="12" maxlength="128" required @error('password_confirmation') aria-invalid="true" aria-describedby="credential-password-confirmation-error" @enderror>
                        @error('password_confirmation')<p class="profile-field-error" id="credential-password-confirmation-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="hint" id="credential-password-hint">{{ __('panel.credentials.password_hint') }}</p>
                <p class="hint">{{ __('panel.profile_workspace.recent_auth_hint') }}</p>
                <button class="btn primary" type="submit">{{ __('panel.credentials.save') }}</button>
            </form>
        </section>
    @endif

    @if($profileUser->role->isStaff())
        <section class="card pad profile-section" id="profile-security" aria-labelledby="security-heading">
            <h2 id="security-heading">{{ __('panel.security.title') }}</h2>
            <p class="muted">{{ __('panel.security.intro') }}</p>
            @if(filled($profileUser->phone_hash))<p class="notice">{{ __('panel.security.otp_primary') }}</p>@endif
            <p class="hint">{{ __('panel.profile_workspace.recent_auth_hint') }}</p>

            <h3>{{ __('panel.security.totp_title') }}</h3>
            <p class="muted">{{ $mfaConfigured ? __('panel.security.totp_on') : __('panel.security.totp_off') }}</p>

            @if(!empty($newRecoveryCodes))
                <div class="notice" role="status">
                    <strong>{{ __('panel.security.recovery_title') }}</strong>
                    <p>{{ __('panel.security.recovery_notice') }}</p>
                    <div dir="ltr" class="profile-recovery-codes">
                        @foreach($newRecoveryCodes as $code)
                            <code>{{ $code }}</code>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!$mfaConfigured && empty($pendingTotpSecret))
                <form method="post" data-profile-form action="{{ route('panel.profile.security.totp.start', ['locale'=>$locale]) }}">
                    @csrf
                    <button class="btn primary" type="submit">{{ __('panel.security.enable_totp') }}</button>
                </form>
            @elseif(!$mfaConfigured && !empty($pendingTotpSecret))
                <div class="notice">
                    <strong>{{ __('panel.security.setup_title') }}</strong>
                    <p>{{ __('panel.security.setup_help') }}</p>
                    <div class="field">
                        <label for="totp-manual-key">{{ __('panel.security.manual_key') }}</label>
                        <input id="totp-manual-key" value="{{ $pendingTotpSecret }}" readonly dir="ltr" data-select-value autocomplete="off" spellcheck="false">
                        <button class="btn profile-copy" type="button" data-copy-value="#totp-manual-key" hidden>{{ __('panel.profile_workspace.copy_key') }}</button>
                    </div>
                    <div class="field">
                        <label for="totp-authenticator-uri">{{ __('panel.security.authenticator_uri') }}</label>
                        <input id="totp-authenticator-uri" value="{{ $pendingTotpUri }}" readonly dir="ltr" data-select-value autocomplete="off" spellcheck="false">
                        <button class="btn profile-copy" type="button" data-copy-value="#totp-authenticator-uri" hidden>{{ __('panel.profile_workspace.copy_uri') }}</button>
                    </div>
                </div>
                <form method="post" data-profile-form action="{{ route('panel.profile.security.totp.confirm', ['locale'=>$locale]) }}">
                    @csrf
                    <div class="field">
                        <label for="totp-confirm">{{ __('panel.security.confirm_code') }}</label>
                        <input id="totp-confirm" name="totp_code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" minlength="6" required @error('totp_code') aria-invalid="true" aria-describedby="totp-confirm-error" @enderror>
                        @error('totp_code')<p class="profile-field-error" id="totp-confirm-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="profile-actions">
                        <button class="btn primary" type="submit">{{ __('panel.security.confirm') }}</button>
                    </div>
                </form>
                <form method="post" data-profile-form action="{{ route('panel.profile.security.totp.cancel', ['locale'=>$locale]) }}" class="profile-spaced">
                    @csrf
                    <button class="btn" type="submit">{{ __('panel.security.cancel') }}</button>
                </form>
            @else
                <details>
                    <summary>{{ __('panel.security.disable') }}</summary>
                    <p class="muted">{{ __('panel.security.disable_help') }}</p>
                    <form method="post" data-profile-form action="{{ route('panel.profile.security.totp.disable', ['locale'=>$locale]) }}" data-confirm="{{ __('panel.profile_workspace.confirm_totp_disable') }}">
                        @csrf
                        <div class="form-grid">
                            <div class="field">
                                <label for="totp-disable">{{ __('panel.security.confirm_code') }}</label>
                                <input id="totp-disable" @error('totp_code') aria-invalid="true" aria-describedby="totp-disable-error" @enderror name="totp_code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6">
                                @error('totp_code')<p class="profile-field-error" id="totp-disable-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="field">
                                <label for="recovery-disable">{{ __('panel.security.recovery_code') }}</label>
                                <input id="recovery-disable" name="recovery_code" dir="ltr" autocomplete="off" @error('recovery_code') aria-invalid="true" aria-describedby="recovery-disable-error" @enderror>
                                @error('recovery_code')<p class="profile-field-error" id="recovery-disable-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button class="btn danger" type="submit">{{ __('panel.security.disable') }}</button>
                    </form>
                </details>
            @endif

            <hr class="profile-divider">
            <h3>{{ __('panel.security.passkey_title') }}</h3>
            <p class="muted">{{ __('panel.profile_workspace.passkey_intro') }}</p>
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
                    <p class="muted profile-spaced">{{ __('panel.security.passkey_none') }}</p>
                @else
                    <div class="task-grid compact profile-records">
                        @foreach($passkeys as $passkey)
                            <article class="task-card">
                                <div class="task-card-main">
                                    <strong>{{ $passkey->name }}</strong>
                                    <span class="task-meta">
                                        {{ __('panel.security.passkey_created') }}
                                        @if($passkey->created_at)
                                            <time datetime="{{ $passkey->created_at->toIso8601String() }}"><bdi>{{ $passkey->created_at->copy()->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</bdi></time>
                                        @else
                                            {{ __('panel.profile_workspace.not_recorded') }}
                                        @endif
                                        @if($passkey->last_used_at)
                                            · {{ __('panel.security.passkey_last_used') }} <time datetime="{{ $passkey->last_used_at->toIso8601String() }}"><bdi>{{ $passkey->last_used_at->copy()->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</bdi></time>
                                        @endif
                                    </span>
                                </div>
                                <form method="post" data-profile-form action="{{ route('passkey.destroy', ['passkey'=>$passkey->id]) }}" data-confirm="{{ __('panel.profile_workspace.confirm_passkey_delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit">{{ __('panel.security.passkey_delete') }}</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                @endif
                <p class="hint profile-spaced">{{ __('panel.security.passkey_reauth_hint') }}</p>
                <p class="hint">{{ __('panel.profile_workspace.timezone_hint') }}</p>
            </div>
        </section>
    @endif

    <section class="card pad profile-section" id="profile-sessions" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading">{{ __('panel.sessions.title') }}</h2>
        <p class="muted profile-intro">{{ __('panel.sessions.intro') }}</p>
        @if(($sessions ?? collect())->isEmpty())
            <p class="muted">{{ __('panel.sessions.empty') }}</p>
        @else
            <div class="task-grid compact profile-records">
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
                            <form method="post" data-profile-form action="{{ route('panel.profile.sessions.revoke', ['locale' => $locale, 'session' => $row['id']]) }}" data-confirm="{{ __('panel.sessions.confirm_one') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn danger sm" type="submit">{{ __('panel.sessions.revoke') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="profile-actions profile-spaced">
                <form method="post" data-profile-form action="{{ route('panel.profile.sessions.revoke_others', ['locale' => $locale]) }}" data-confirm="{{ __('panel.sessions.confirm_others') }}">
                    @csrf
                    <button class="btn" type="submit">{{ __('panel.sessions.revoke_others') }}</button>
                </form>
                <form method="post" data-profile-form action="{{ route('panel.profile.sessions.revoke_all', ['locale' => $locale]) }}" data-confirm="{{ __('panel.sessions.confirm_all') }}">
                    @csrf
                    <button class="btn danger" type="submit">{{ __('panel.sessions.revoke_all') }}</button>
                </form>
            </div>
        @endif
    </section>
@endif
</div>
@endsection
