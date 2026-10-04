@extends('panel.layout')
@section('title', __('patient_portal.profile_title'))
@section('heading', __('patient_portal.profile_title'))
@push('scripts')<link rel="stylesheet" href="/assets/patient-portal-20261004.css"><script src="/assets/patient-location-20261004.js" defer></script>@endpush
@section('content')
<section class="patient-portal patient-section"><h2>{{ __('patient_portal.profile_title') }}</h2><p>{{ __('patient_portal.profile_intro') }}</p>
@if($errors->any())<div class="notice error" role="alert"><h3>{{ __('patient_portal.check_errors') }}</h3>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form action="{{ route('patient.profile.save', ['locale' => $locale]) }}" method="post">@csrf<input type="hidden" name="version" value="{{ old('version', $profile?->version ?? 0) }}">
<div class="patient-location-grid">
<div class="field"><label for="patient-name">{{ __('panel.profile.name') }}</label><input id="patient-name" name="name" required maxlength="80" autocomplete="name" value="{{ old('name', $patient->name) }}"></div>
<div class="field"><label for="patient-phone">{{ __('patient_portal.phone') }}</label><input id="patient-phone" value="{{ $patient->phone }}" readonly dir="ltr" autocomplete="tel"></div>
<div class="field"><label for="patient-email">{{ __('patient_portal.email') }}</label><input id="patient-email" name="contact_email" type="email" maxlength="254" autocomplete="email" dir="ltr" value="{{ old('contact_email', $profile?->contact_email) }}"></div>
<div class="field"><label for="birth-date">{{ __('patient_portal.birth_date') }}</label><input id="birth-date" name="birth_date" type="date" min="1900-01-01" max="{{ now()->format('Y-m-d') }}" value="{{ old('birth_date', $profile?->birth_date) }}"></div>
<div class="field"><label for="postal-code">{{ __('patient_portal.postal_code') }}</label><input id="postal-code" name="postal_code" inputmode="numeric" maxlength="10" value="{{ old('postal_code', $profile?->postal_code) }}"></div>
<div class="field"><label for="profile-contact-time">{{ __('request.contact_time') }}</label><select id="profile-contact-time" name="preferred_contact_time">@foreach(['any','morning','midday','evening','night'] as $time)<option value="{{ $time }}" @selected(old('preferred_contact_time', $profile?->preferred_contact_time ?? 'any') === $time)>{{ __('request.times.'.$time) }}</option>@endforeach</select></div>
</div>
@include('patient-portal.location-fields', ['locationProfile' => $profile])
<div class="actions"><button class="btn primary" type="submit">{{ __('patient_portal.save') }}</button><a class="btn" href="{{ route('panel.profile', ['locale' => $locale]) }}">{{ __('patient_portal.security') }}</a><a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('patient_portal.return_home') }}</a></div>
</form></section>
@endsection
