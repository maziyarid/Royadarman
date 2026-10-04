@extends('panel.layout')
@section('title', __('patient_portal.home_title'))
@section('heading', __('patient_portal.home_title'))
@push('scripts')<link rel="stylesheet" href="/assets/patient-portal-20261004.css">@endpush
@section('content')
<div class="patient-portal" data-patient-portal>
<section class="patient-hero"><div><p class="patient-eyebrow">{{ __('panel.brand') }}</p><h2>{{ $patient->name ?: __('patient_portal.home_title') }}</h2><p>{{ __('patient_portal.intro') }}</p></div><a class="btn primary" href="{{ route('patient.request.create', ['locale' => $locale]) }}">{{ __('patient_portal.new_request') }}</a></section>
<nav class="patient-actions" aria-label="{{ __('patient_portal.home_title') }}">
<a class="patient-action" href="{{ route('patient.profile', ['locale' => $locale]) }}"><span aria-hidden="true">◎</span><strong>{{ __('patient_portal.profile_title') }}</strong><small>{{ __('patient_portal.edit_profile') }}</small></a>
<a class="patient-action" href="#patient-documents"><span aria-hidden="true">▧</span><strong>{{ __('patient_portal.documents') }}</strong><small>{{ __('patient_portal.upload') }}</small></a>
<a class="patient-action" href="#patient-requests"><span aria-hidden="true">☷</span><strong>{{ __('patient_portal.requests') }}</strong><small>{{ __('patient_portal.open') }}</small></a>
<a class="patient-action" href="#patient-messages"><span aria-hidden="true">✉</span><strong>{{ __('patient_portal.complaints') }}</strong><small>{{ __('patient_portal.send') }}</small></a>
</nav>
<section class="patient-profile-note"><div><strong>{{ __('patient_portal.profile_title') }}</strong><p>{{ __('patient_portal.'.($profileComplete ? 'profile_ready' : 'profile_missing')) }}</p></div><a class="btn" href="{{ route('patient.profile', ['locale' => $locale]) }}">{{ __('patient_portal.edit_profile') }}</a></section>
<section class="patient-stats" aria-label="{{ __('patient_portal.requests') }}"><div><strong>{{ $cases->total() }}</strong><span>{{ __('patient_portal.all_requests') }}</span></div><div><strong>{{ $activeCases }}</strong><span>{{ __('patient_portal.active') }}</span></div><div><strong>{{ $documentCount }}</strong><span>{{ __('patient_portal.documents') }}</span></div></section>
<section class="patient-section" id="patient-requests"><h2>{{ __('ui.dashboard.my_cases') }}</h2><p class="hint">{{ __('patient_portal.process') }}</p><p>{{ __('patient_portal.process_help') }}</p>
@forelse($cases as $case)
<article class="patient-request-row"><div><strong><bdi>{{ $case->public_reference }}</bdi></strong><p>{{ __('ui.dashboard.service.'.$case->service_type->value) }} · <span class="badge">{{ __('ui.dashboard.status.'.$case->status->value) }}</span></p><small><time datetime="{{ $case->updated_at->toIso8601String() }}"><bdi>{{ $case->updated_at->timezone('Asia/Tehran')->format('Y-m-d H:i') }}</bdi></time></small></div><a class="btn" href="{{ route('panel.case', ['locale' => $locale, 'case' => $case->id]) }}">{{ __('patient_portal.open') }}</a></article>
@empty<p class="patient-empty">{{ __('ui.dashboard.no_cases') }}</p><a class="btn primary" href="{{ route('patient.request.create', ['locale' => $locale]) }}">{{ __('patient_portal.new_request') }}</a>@endforelse
<div class="patient-pagination">{{ $cases->links() }}</div></section>
<section class="patient-section" id="patient-documents"><h2>{{ __('patient_portal.documents') }}</h2><p>{{ __('patient_portal.upload_help') }}</p>
@forelse($cases as $case)<div class="patient-request-row"><div><bdi>{{ $case->public_reference }}</bdi> · {{ $case->documents_count }} {{ __('patient_portal.files_count') }}</div><a class="btn" href="{{ route('panel.case', ['locale' => $locale, 'case' => $case->id]) }}#patient-case-upload">{{ __('patient_portal.upload') }}</a></div>@empty<p class="patient-empty">{{ __('patient_portal.no_cases') }}</p>@endforelse
</section>
<section class="patient-section" id="patient-messages"><h2>{{ __('patient_portal.complaints') }}</h2><p>{{ __('patient_portal.complaint_help') }}</p>
@if($errors->any())<div class="notice error" role="alert">{{ __('patient_portal.check_errors') }}@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ route('panel.support.store', ['locale' => $locale]) }}" class="patient-message-form">@csrf<input type="hidden" name="category" value="general">
<div class="field"><label for="support-subject">{{ __('patient_portal.subject') }}</label><input id="support-subject" name="subject" value="{{ old('subject') }}" maxlength="200" required></div>
<div class="field"><label for="support-case">{{ __('patient_portal.optional_case') }}</label><select id="support-case" name="case_id"><option value="">{{ __('patient_portal.none') }}</option>@foreach($cases as $case)<option value="{{ $case->id }}" @selected(old('case_id') === $case->id)>{{ $case->public_reference }}</option>@endforeach</select></div>
<div class="field full"><label for="support-message">{{ __('patient_portal.message') }}</label><textarea id="support-message" name="message" required maxlength="5000" rows="4" dir="auto">{{ old('message') }}</textarea></div><button class="btn primary" type="submit">{{ __('patient_portal.send') }}</button></form>
@forelse($conversations as $conversation)<article class="patient-request-row"><div><strong>{{ $conversation->subject ?: __('patient_portal.complaints') }}</strong><p>{{ __('panel.support.status.'.$conversation->status->value) }}</p></div><a class="btn" href="{{ route('panel.support.show', ['locale' => $locale, 'conversation' => $conversation->id]) }}">{{ __('patient_portal.open') }}</a></article>@empty<p class="patient-empty">{{ __('patient_portal.no_messages') }}</p>@endforelse
<a class="btn" href="{{ route('panel.support.index', ['locale' => $locale]) }}">{{ __('patient_portal.complaints') }}</a></section>
</div>
@endsection
