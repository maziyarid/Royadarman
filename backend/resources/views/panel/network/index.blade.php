<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ __('network.title') }} · Royadarman</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/workspace.css?v=20260913"><script src="/assets/workspace.js?v=20260913" defer></script>
</head>
<body class="workspace-body"><main class="shell">
<header class="top"><div><h1>{{ __('network.title') }}</h1><p>{{ __('network.intro') }}</p></div><a class="btn" href="{{ route('panel', ['locale'=>$locale]) }}">{{ __('network.back') }}</a></header>
@if(session('status'))<div class="notice">{{ session('status') }}</div>@endif
<div class="notice">{{ __('network.provisioning_note') }}</div>

<div class="grid">
<section class="card"><h2>{{ __('network.clinics') }}</h2>
    @forelse($clinics as $clinic)
        <form class="row" method="post" action="{{ route('network.clinic.update', ['locale'=>$locale,'clinic'=>$clinic->id]) }}">@csrf @method('PUT')
            <div class="fields"><div><label>{{ __('network.name') }}</label><input name="name" value="{{ $clinic->name }}" required></div><div><label>{{ __('network.city') }}</label><input name="city" value="{{ $clinic->city }}" required></div><div><label>{{ __('network.area') }}</label><input name="area_code" value="{{ $clinic->area_code }}"></div><div><label>{{ __('network.active') }}</label><select name="is_active"><option value="1" @selected($clinic->is_active)>1</option><option value="0" @selected(!$clinic->is_active)>0</option></select></div></div>
            <div class="actions"><button class="btn" type="submit">{{ __('network.save') }}</button></div>
        </form>
    @empty <p class="meta">{{ __('network.none') }}</p> @endforelse
    <form class="row" method="post" action="{{ route('network.clinic.store', ['locale'=>$locale]) }}">@csrf
        <strong>{{ __('network.new_clinic') }}</strong><div class="fields"><div><label>{{ __('network.name') }}</label><input name="name" required></div><div><label>{{ __('network.city') }}</label><input name="city" value="Tehran" required></div><div><label>{{ __('network.area') }}</label><input name="area_code"></div></div><div class="actions"><button class="btn primary" type="submit">{{ __('network.new_clinic') }}</button></div>
    </form>
</section>

<section class="card"><h2>{{ __('network.staff') }}</h2>
    @forelse($staff as $person)
        <div class="row"><strong>{{ $person->name ?: '#'.$person->id }}</strong> <span class="badge">{{ $person->role }}</span><div class="meta">{{ $person->is_active ? __('network.active') : __('network.status').': inactive' }}</div>
        @if($person->role === 'clinician')
            <form method="post" action="{{ route('network.practitioner.save', ['locale'=>$locale,'user'=>$person->id]) }}">@csrf
                <div class="fields"><div><label>{{ __('network.licence') }}</label><input name="licence_number" required autocomplete="off"></div><div><label>{{ __('network.credential_status') }}</label><select name="credential_status">@foreach(['pending','verified','revoked'] as $state)<option value="{{ $state }}" @selected(($person->credential_status ?: 'pending') === $state)>{{ __('network.credential_states.'.$state) }}</option>@endforeach</select></div><div><label>{{ __('network.expires') }}</label><input type="datetime-local" name="expires_at" value="{{ $person->expires_at ? \Carbon\Carbon::parse($person->expires_at)->format('Y-m-d\TH:i') : '' }}"></div></div>
                <div class="actions"><button class="btn" type="submit">{{ __('network.save') }}</button></div>
            </form>
        @endif
        </div>
    @empty <p class="meta">{{ __('network.none') }}</p> @endforelse
</section>
</div>

<section class="card"><h2>{{ __('network.memberships') }}</h2>
    @forelse($memberships as $membership)
        <div class="row"><strong>{{ $membership->clinic_name }}</strong> · {{ $membership->user_name ?: '#'.$membership->user_id }} <span class="badge">{{ __('network.membership_roles.'.$membership->membership_role) }}</span><div class="meta">{{ $membership->active_from }} → {{ $membership->active_until ?: '∞' }}</div>
            @if(!$membership->active_until || \Carbon\Carbon::parse($membership->active_until)->isFuture())<form class="actions" method="post" action="{{ route('network.membership.revoke', ['locale'=>$locale,'membership'=>$membership->id]) }}">@csrf @method('DELETE')<button class="btn danger" type="submit">{{ __('network.revoke') }}</button></form>@endif
        </div>
    @empty <p class="meta">{{ __('network.none') }}</p> @endforelse

    <form class="row" method="post" action="{{ route('network.membership.store', ['locale'=>$locale]) }}">@csrf
        <strong>{{ __('network.add_membership') }}</strong>
        <div class="fields"><div><label>{{ __('network.clinic') }}</label><select name="clinic_id" required>@foreach($clinics->where('is_active', true) as $clinic)<option value="{{ $clinic->id }}">{{ $clinic->name }}</option>@endforeach</select></div><div><label>{{ __('network.member') }}</label><select name="user_id" required>@foreach($staff->where('is_active', true)->whereIn('role',['clinician','clinic_rep']) as $person)<option value="{{ $person->id }}">{{ $person->name ?: '#'.$person->id }} · {{ $person->role }}</option>@endforeach</select></div><div><label>{{ __('network.membership_role') }}</label><select name="membership_role"><option value="contact">{{ __('network.membership_roles.contact') }}</option><option value="reviewer">{{ __('network.membership_roles.reviewer') }}</option></select></div><div><label>{{ __('network.active_until') }}</label><input type="datetime-local" name="active_until"></div></div>
        <div class="actions"><button class="btn primary" type="submit">{{ __('network.add_membership') }}</button></div>
    </form>
</section>
</main></body></html>
