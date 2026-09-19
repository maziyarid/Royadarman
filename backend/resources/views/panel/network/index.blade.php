@extends('panel.layout')
@section('title', __('network.title'))
@section('heading', __('network.title'))
@section('content')
<p class="hint">{{ __('network.intro') }}</p>
<div class="notice">{{ __('network.provisioning_note') }}</div>

<form class="filters" method="get">
    <div class="field">
        <label for="q">{{ __('network.search') }}</label>
        <input id="q" name="q" value="{{ $search }}" placeholder="{{ __('network.search_placeholder') }}">
    </div>
    <button class="btn" type="submit">{{ __('network.apply') }}</button>
</form>

<div class="grid">
    <section class="card pad">
        <h2>{{ __('network.clinics') }}</h2>
        @forelse($clinics as $clinic)
            @if(!empty($clinic->synthetic_demo_key))
                <div class="row">
                    <strong>{{ $clinic->name }}</strong>
                    <span class="badge">{{ __('panel.demo_label') }}</span>
                    <div class="meta">{{ $clinic->city }} · {{ $clinic->area_code }}</div>
                    <p class="hint">{{ __('network.demo_locked') }}</p>
                </div>
            @else
                <form class="row" method="post" action="{{ route('network.clinic.update', ['locale' => $locale, 'clinic' => $clinic->id]) }}">
                    @csrf @method('PUT')
                    <div class="fields">
                        <div class="field"><label>{{ __('network.name') }}</label><input name="name" value="{{ $clinic->name }}" required></div>
                        <div class="field"><label>{{ __('network.city') }}</label><input name="city" value="{{ $clinic->city }}" required></div>
                        <div class="field"><label>{{ __('network.area') }}</label><input name="area_code" value="{{ $clinic->area_code }}"></div>
                        <div class="field"><label>{{ __('network.latitude') }}</label><input name="latitude" inputmode="decimal" value="{{ $clinic->latitude }}"></div>
                        <div class="field"><label>{{ __('network.longitude') }}</label><input name="longitude" inputmode="decimal" value="{{ $clinic->longitude }}"></div>
                        <p class="hint">{{ __('network.location_hint') }}</p>
                        <div class="field">
                            <label>{{ __('network.active') }}</label>
                            <select name="is_active">
                                <option value="1" @selected($clinic->is_active)>{{ __('network.yes') }}</option>
                                <option value="0" @selected(!$clinic->is_active)>{{ __('network.no') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="actions"><button class="btn" type="submit">{{ __('network.save') }}</button></div>
                </form>
            @endif
        @empty
            <p class="meta">{{ __('network.none') }}</p>
        @endforelse
        <form class="row" method="post" action="{{ route('network.clinic.store', ['locale' => $locale]) }}">
            @csrf
            <strong>{{ __('network.new_clinic') }}</strong>
            <div class="fields">
                <div class="field"><label>{{ __('network.name') }}</label><input name="name" required></div>
                <div class="field"><label>{{ __('network.city') }}</label><input name="city" value="Tehran" required></div>
                <div class="field"><label>{{ __('network.area') }}</label><input name="area_code"></div>
                <div class="field"><label>{{ __('network.latitude') }}</label><input name="latitude" inputmode="decimal"></div>
                <div class="field"><label>{{ __('network.longitude') }}</label><input name="longitude" inputmode="decimal"></div>
            </div>
            <p class="hint">{{ __('network.location_hint') }}</p>
            <div class="actions"><button class="btn primary" type="submit">{{ __('network.new_clinic') }}</button></div>
        </form>
    </section>

    <section class="card pad">
        <h2>{{ __('network.staff') }}</h2>
        @forelse($staff as $person)
            <div class="row">
                <strong>{{ $person->name ?: '#'.$person->id }}</strong>
                <span class="badge">{{ __('ui.dashboard.roles.'.str_replace('tech_admin', 'tech_admin', $person->role)) }}</span>
                <div class="meta">{{ $person->is_active ? __('network.active') : __('network.inactive') }}</div>
                @if(in_array($person->email, $demoEmails, true))
                    <p class="hint">{{ __('network.demo_identity') }}</p>
                @elseif($person->role === 'clinician')
                    <form method="post" action="{{ route('network.practitioner.save', ['locale' => $locale, 'user' => $person->id]) }}">
                        @csrf
                        <div class="fields">
                            <div class="field"><label>{{ __('network.licence') }}</label><input name="licence_number" required autocomplete="off"></div>
                            <div class="field">
                                <label>{{ __('network.credential_status') }}</label>
                                <select name="credential_status">
                                    @foreach(['pending','verified','revoked'] as $state)
                                        <option value="{{ $state }}" @selected(($person->credential_status ?: 'pending') === $state)>{{ __('network.credential_states.'.$state) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label>{{ __('network.expires') }}</label>
                                <input type="datetime-local" name="expires_at" value="{{ $person->expires_at ? \Carbon\Carbon::parse($person->expires_at)->format('Y-m-d\TH:i') : '' }}">
                            </div>
                        </div>
                        <div class="actions"><button class="btn" type="submit">{{ __('network.save') }}</button></div>
                    </form>
                @endif
            </div>
        @empty
            <p class="meta">{{ __('network.none') }}</p>
        @endforelse
    </section>
</div>

<section class="card pad">
    <h2>{{ __('network.memberships') }}</h2>
    @forelse($memberships as $membership)
        <div class="row">
            <strong>{{ $membership->clinic_name }}</strong>
            · {{ $membership->user_name ?: '#'.$membership->user_id }}
            <span class="badge">{{ __('network.membership_roles.'.$membership->membership_role) }}</span>
            <div class="meta"><bdi>{{ $membership->active_from }}</bdi> → <bdi>{{ $membership->active_until ?: '∞' }}</bdi></div>
            @if(empty($membership->clinic_demo_key) && (!$membership->active_until || \Carbon\Carbon::parse($membership->active_until)->isFuture()))
                <form class="actions" method="post" action="{{ route('network.membership.revoke', ['locale' => $locale, 'membership' => $membership->id]) }}" data-confirm="{{ __('network.confirm_revoke') }}">
                    @csrf @method('DELETE')
                    <button class="btn danger" type="submit">{{ __('network.revoke') }}</button>
                </form>
            @elseif(!empty($membership->clinic_demo_key))
                <p class="hint">{{ __('network.demo_locked') }}</p>
            @endif
        </div>
    @empty
        <p class="meta">{{ __('network.none') }}</p>
    @endforelse

    <form class="row" method="post" action="{{ route('network.membership.store', ['locale' => $locale]) }}">
        @csrf
        <strong>{{ __('network.add_membership') }}</strong>
        <div class="fields">
            <div class="field">
                <label>{{ __('network.clinic') }}</label>
                <select name="clinic_id" required>
                    @forelse($clinics->filter(fn ($clinic) => empty($clinic->synthetic_demo_key) && $clinic->is_active) as $clinic)
                        <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                    @empty
                        <option value="" disabled selected>{{ __('network.none') }}</option>
                    @endforelse
                </select>
            </div>
            <div class="field">
                <label>{{ __('network.member') }}</label>
                <select name="user_id" required>
                    @foreach($staff->where('is_active', true)->whereIn('role', ['clinician', 'clinic_rep']) as $person)
                        @continue(in_array($person->email, $demoEmails, true))
                        <option value="{{ $person->id }}">{{ $person->name ?: '#'.$person->id }} · {{ $person->role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ __('network.membership_role') }}</label>
                <select name="membership_role">
                    <option value="contact">{{ __('network.membership_roles.contact') }}</option>
                    <option value="reviewer">{{ __('network.membership_roles.reviewer') }}</option>
                </select>
            </div>
            <div class="field">
                <label>{{ __('network.active_until') }}</label>
                <input type="datetime-local" name="active_until">
            </div>
        </div>
        <div class="actions"><button class="btn primary" type="submit">{{ __('network.add_membership') }}</button></div>
    </form>
</section>
    <section class="card pad">
        <h2>{{ __('network.capabilities') }}</h2>
        <p class="hint">{{ __('network.capability_hint') }}</p>
        @forelse($capabilities as $cap)
            <div class="row">
                <strong>{{ $cap->clinic_name }}</strong>
                <div class="meta">{{ $cap->service_type }} · {{ $cap->suitability_status }} · {{ $cap->attested_at }}</div>
            </div>
        @empty
            <p class="meta">{{ __('network.none') }}</p>
        @endforelse
        <form class="row" method="post" action="{{ route('network.capability.save', ['locale' => $locale]) }}">
            @csrf
            <strong>{{ __('network.attest_capability') }}</strong>
            <div class="fields">
                <div class="field">
                    <label>{{ __('network.clinic') }}</label>
                    <select name="clinic_id" required>
                        @foreach($clinics->whereNull('synthetic_demo_key') as $clinic)
                            <option value="{{ $clinic->id }}">{{ $clinic->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>{{ __('network.service_type') }}</label>
                    <select name="service_type" required>
                        @foreach($serviceTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>{{ __('network.suitability') }}</label>
                    <select name="suitability_status" required>
                        @foreach($suitabilityStatuses as $status)
                            <option value="{{ $status->value }}">{{ __('network.suitability_states.'.$status->value) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="actions"><button class="btn" type="submit">{{ __('network.attest_capability') }}</button></div>
        </form>
    </section>

@endsection
