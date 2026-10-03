@php
    $serviceValue = $case->service_type instanceof \BackedEnum ? $case->service_type->value : $case->service_type;
    $statusValue = $case->status instanceof \BackedEnum ? $case->status->value : $case->status;
@endphp
@extends('panel.layout')
@section('title', __('panel_case.case').' '.$case->public_reference)
@section('heading')
    {{ __('panel_case.case') }} <bdi>{{ $case->public_reference }}</bdi>
@endsection
@section('actions')
    <a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('panel_case.back') }}</a>
@endsection
@push('scripts')
<script src="/assets/panel-case.js?v=20260915" defer></script>
@if($roleKey === 'patient')
<link rel="stylesheet" href="/assets/patient-case.css?v=20261003">
@endif
@if($roleKey === 'clinician')
<link rel="stylesheet" href="/assets/clinician-case.css?v=20261003">
@endif
@endpush
@section('content')
<div data-panel-case data-locale="{{ $locale }}" data-case-id="{{ $case->id }}" data-case-version="{{ (int) $case->version }}" data-source-language="{{ $case->source_language }}" data-error="{{ __('panel_case.error') }}" data-accept="{{ __('panel_case.accept_policy') }}" data-cancel="{{ __('panel_case.decline') }}">
<div id="notice" class="notice is-hidden" role="status"></div>
<section class="card pad">
    <div class="facts">
        <div class="fact"><small>{{ __('panel_case.service') }}</small>{{ __('ui.dashboard.service.'.$serviceValue) }}</div>
        <div class="fact"><small>{{ __('panel_case.status') }}</small><span class="badge {{ $statusValue }}">{{ __('ui.dashboard.status.'.$statusValue) }}</span></div>
    </div>
</section>

@if(!empty($shared))
<section class="card pad">
    <h2>{{ __('panel_case.details') }}</h2>
    <div class="facts">
        @foreach(['patient_name'=>__('panel_case.patient_name'),'patient_mobile'=>__('panel_case.patient_mobile'),'tehran_area'=>__('panel_case.area'),'preferred_contact_time'=>__('panel_case.contact_time'),'contact_reason'=>__('panel_case.contact_reason'),'budget_band'=>__('panel_case.budget'),'grant_expires_at'=>__('panel_case.grant_expires')] as $key=>$label)
            @if(array_key_exists($key, $shared) && $shared[$key] !== null)
                <div class="fact"><small>{{ $label }}</small><bdi>{{ $shared[$key] }}</bdi></div>
            @endif
        @endforeach
    </div>
</section>
@endif

@if($roleKey === 'patient')
@php
    $patientDate = static function ($value) {
        if ($value instanceof \DateTimeInterface) return \DateTimeImmutable::createFromInterface($value);
        if (!is_string($value) || strlen($value) > 40 || !preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) return null;
        try {
            $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
            return $date && \DateTimeImmutable::getLastErrors() === false ? $date : null;
        } catch (\Throwable) { return null; }
    };
    $processing = $documents->contains(fn ($document) => in_array($document->status, ['quarantined', 'scanning'], true));
    $reviewState = $reviews->isNotEmpty() ? 'released' : ($processing ? 'processing' : ($documents->isEmpty() ? 'no_documents' : 'awaiting_review'));
    $stateCopy = ['released' => 'released', 'processing' => 'processing', 'no_documents' => 'no_document', 'awaiting_review' => 'no_review'];
    $stateHelp = ['released' => 'released_help', 'processing' => 'processing_help', 'no_documents' => 'no_document_help', 'awaiting_review' => 'review_wait_help'];
    $reviewFields = ['image_adequacy' => 'image_adequacy', 'observations' => 'observations', 'limitations' => 'limitations', 'options' => 'options', 'recommended_next_step' => 'next_step'];
@endphp
<div class="patient-case">
    <section class="patient-case-hero" aria-labelledby="patient-case-title">
        <p class="patient-case-eyebrow">{{ __('patient_case.eyebrow') }}</p>
        <h2 id="patient-case-title">{{ __('patient_case.title') }}</h2>
        <p>{{ __('patient_case.intro') }}</p>
        <div class="patient-case-state" data-review-state="{{ $reviewState }}">
            <strong>{{ __('patient_case.'.$stateCopy[$reviewState]) }}</strong>
            <p>{{ __('patient_case.'.$stateHelp[$reviewState]) }}</p>
        </div>
    </section>
    <nav class="patient-case-nav" aria-label="{{ __('patient_case.sections') }}">
        @if(empty($isDemo))<a href="#patient-case-upload">{{ __('patient_case.upload_section') }}</a>@endif
        <a href="#patient-case-documents">{{ __('patient_case.documents_section') }}</a>
        <a href="#patient-case-reviews">{{ __('patient_case.reviews_section') }}</a>
        <a href="#patient-case-referrals">{{ __('patient_case.referrals_section') }}</a>
    </nav>
    @if(empty($isDemo) && $statusValue === 'draft')
        <section class="patient-case-card">
            <h2>{{ __('panel_case.submit') }}</h2>
            <p class="muted">{{ __('panel_case.submit_help') }}</p>
            <button class="btn primary" type="button" data-submit-case>{{ __('panel_case.submit') }}</button>
        </section>
    @endif
    @if(empty($isDemo))
        <section class="patient-case-card" id="patient-case-upload" aria-labelledby="patient-case-upload-title">
            <h2 id="patient-case-upload-title">{{ __('panel_case.upload') }}</h2>
            <p class="patient-case-note" id="patient-case-upload-help">{{ __('patient_case.upload_help') }}</p>
            <form id="upload-form">
                <div class="field">
                    <label for="opg-file">{{ __('panel_case.file') }}</label>
                    <input id="opg-file" name="document" type="file" accept="image/jpeg,image/png" required aria-describedby="patient-case-upload-help">
                </div>
                <button class="btn primary" type="submit">{{ __('panel_case.upload') }}</button>
            </form>
            <noscript><p class="patient-case-note">{{ __('patient_case.javascript_help') }}</p></noscript>
        </section>
    @endif
    <section class="patient-case-card" id="patient-case-documents" aria-labelledby="patient-case-documents-title">
        <h2 id="patient-case-documents-title">{{ __('patient_case.documents_section') }}</h2>
        <p class="patient-case-note">{{ __('patient_case.file_check_note') }}</p>
        <ul class="patient-case-documents">
            @forelse($documents as $document)
                @php
                    $documentStatus = in_array($document->status, ['quarantined', 'scanning', 'approved', 'rejected', 'scan_failed', 'deleted'], true) ? $document->status : 'unknown';
                    $uploadedAt = $patientDate($document->created_at ?? null);
                @endphp
                <li class="patient-case-document">
                    <div>
                        <strong><bdi>{{ $document->original_name ?: __('patient_case.file_name_unknown') }}</bdi></strong>
                        <span class="patient-case-badge" data-document-status="{{ $documentStatus }}">{{ __('patient_case.document_status.'.$documentStatus) }}</span>
                        <p class="patient-case-note">{{ __('patient_case.uploaded_at') }}:
                            @if($uploadedAt)<time dir="ltr" datetime="{{ $uploadedAt->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM) }}">{{ $uploadedAt->setTimezone(new \DateTimeZone('Asia/Tehran'))->format('Y-m-d H:i') }}</time>@else{{ __('patient_case.date_unknown') }}@endif
                        </p>
                        @if($documentStatus === 'scan_failed')
                            <p class="patient-case-note">{{ __('patient_case.scan_failed_help') }} <a href="{{ route('panel.support.index', ['locale' => $locale]) }}">{{ __('patient_case.support') }}</a></p>
                        @endif
                    </div>
                    @if(empty($isDemo) && $document->status === 'approved')
                        <a class="btn" target="_blank" rel="noopener" href="/api/v1/cases/{{ $case->id }}/documents/{{ $document->id }}/content">{{ __('panel_case.open_document') }}</a>
                    @endif
                </li>
            @empty
                <li class="patient-case-empty">{{ __('patient_case.no_document') }}</li>
            @endforelse
        </ul>
        <p class="patient-case-note">{{ __('patient_case.timezone_hint') }}</p>
    </section>
    <section class="patient-case-card" id="patient-case-reviews" aria-labelledby="patient-case-reviews-title">
        <h2 id="patient-case-reviews-title">{{ __('patient_case.reviews_section') }}</h2>
        <p class="patient-case-note">{{ __('patient_case.review_help') }}</p>
        @forelse($reviews as $review)
            @php
                $sourceLanguage = in_array($review->source_language ?? null, ['fa', 'ar', 'en'], true) ? $review->source_language : null;
                $signedAt = $patientDate($review->signed_at ?? null);
                $publishedAt = $patientDate($review->published_at ?? null);
                $revision = is_int($review->revision_number ?? null) && $review->revision_number > 0 ? $review->revision_number : __('patient_case.not_recorded');
            @endphp
            <article class="patient-case-review" aria-labelledby="patient-review-{{ $review->id }}">
                <header><h3 id="patient-review-{{ $review->id }}">{{ __('patient_case.revision', ['number' => $revision]) }}</h3></header>
                <dl class="patient-case-review-meta">
                    <div><dt>{{ __('patient_case.source_language') }}</dt><dd>{{ $sourceLanguage ? __('patient_case.languages.'.$sourceLanguage) : __('patient_case.language_unknown') }}</dd></div>
                    @foreach(['signed_at' => $signedAt, 'published_at' => $publishedAt] as $label => $date)
                        <div><dt>{{ __('patient_case.'.$label) }}</dt><dd>@if($date)<time dir="ltr" datetime="{{ $date->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM) }}">{{ $date->setTimezone(new \DateTimeZone('Asia/Tehran'))->format('Y-m-d H:i') }}</time>@else{{ __('patient_case.date_unknown') }}@endif</dd></div>
                    @endforeach
                </dl>
                <dl class="patient-case-review-fields">
                    @foreach($reviewFields as $field => $label)
                        @php $narrative = $review->{$field} ?? null; @endphp
                        <div data-review-field="{{ $field }}"><dt>{{ __('panel_case.'.$label) }}</dt><dd @if($sourceLanguage) lang="{{ $sourceLanguage }}" @endif dir="{{ $sourceLanguage ? (in_array($sourceLanguage, ['fa', 'ar'], true) ? 'rtl' : 'ltr') : 'auto' }}">{{ is_string($narrative) && trim($narrative) !== '' ? $narrative : __('patient_case.not_recorded') }}</dd></div>
                    @endforeach
                </dl>
            </article>
        @empty
            <div class="patient-case-empty"><strong>{{ __('patient_case.no_review') }}</strong><p>{{ __('patient_case.review_wait_help') }}</p></div>
        @endforelse
        <p class="patient-case-note">{{ __('patient_case.timezone_hint') }}</p>
    </section>
    <section class="patient-case-card" id="patient-case-referrals" aria-labelledby="patient-case-referrals-title">
        <h2 id="patient-case-referrals-title">{{ __('panel_case.referrals') }}</h2>
        <p class="patient-case-note">{{ __('patient_case.referral_help') }}</p>
        @forelse($referrals as $referral)
            @php
                $referralStatus = $referral->effective_status ?? $referral->status;
                $referralLabel = in_array($referralStatus, ['proposed', 'accepted', 'declined', 'withdrawn'], true) ? __('panel_case.referral_status.'.$referralStatus) : __('patient_case.referral_unknown');
            @endphp
            <div class="patient-case-referral">
                <div><strong>{{ $referral->clinic_name }}</strong><span class="patient-case-badge">{{ $referralLabel }}</span></div>
                @if(empty($isDemo) && $referral->status === 'proposed' && empty($referral->withdrawn_at))
                    <div class="actions">
                        <button class="btn primary" type="button" data-referral-decision="accepted" data-referral-id="{{ $referral->id }}" data-language="{{ $referral->source_language }}">{{ __('panel_case.accept') }}</button>
                        <button class="btn danger" type="button" data-referral-decision="declined" data-referral-id="{{ $referral->id }}" data-language="{{ $referral->source_language }}">{{ __('panel_case.decline') }}</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="patient-case-empty">{{ __('panel_case.no_items') }}</p>
        @endforelse
    </section>
</div>
@endif

@if($roleKey === 'coordinator')
<section class="card pad">
    <h2>{{ __('panel_case.coordinator_actions') }}</h2>
    @if(empty($isDemo))
        <div class="grid">
            @if(count($allowedStatuses))
                <form id="status-form">
                    <h3>{{ __('panel_case.change_status') }}</h3>
                    <div class="field">
                        <select name="status" required>
                            @foreach($allowedStatuses as $target)
                                <option value="{{ $target->value }}">{{ __('ui.dashboard.status.'.$target->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><input name="reason" maxlength="1000" placeholder="{{ __('panel_case.reason') }}"></div>
                    <button class="btn primary">{{ __('panel_case.change_status') }}</button>
                </form>
            @endif
            <form id="assign-form">
                <h3>{{ __('panel_case.assign_clinician') }}</h3>
                <div class="field">
                    <select name="assignee_user_id" required>
                        @foreach($eligibleClinicians as $clinician)
                            <option value="{{ $clinician->id }}">{{ $clinician->name ?: '#'.$clinician->id }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn primary" @disabled($eligibleClinicians->isEmpty())>{{ __('panel_case.assign_clinician') }}</button>
            </form>
            <form id="referral-form">
                <h3>{{ __('panel_case.propose_referral') }}</h3>
                <div class="field">
                    <select name="clinic_id" required>
                        @foreach($clinics as $clinic)
                            <option value="{{ $clinic->id }}">{{ $clinic->name }} · {{ $clinic->city }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><textarea name="reasoning" required maxlength="2000" placeholder="{{ __('panel_case.reason') }}"></textarea></div>
                <button class="btn primary" @disabled($clinics->isEmpty())>{{ __('panel_case.propose_referral') }}</button>
            </form>
        </div>
    @else
        <p class="muted">{{ __('panel.demo_mutations_disabled') }}</p>
    @endif
    <h3>{{ __('panel_case.assignments') }}</h3>
    @forelse($assignments as $assignment)
        <div class="item">{{ $assignment->purpose }} · {{ $assignment->name ?: '#'.$assignment->assignee_user_id }} · {{ $assignment->role }}</div>
    @empty
        <p class="muted">{{ __('panel_case.no_items') }}</p>
    @endforelse
</section>
@endif

@if($roleKey === 'clinician')
@php
    $draftPreviews = $draftReviewPreviews ?? collect();
    $clinicianDate = static function ($value) {
        if ($value instanceof \DateTimeInterface) return \DateTimeImmutable::createFromInterface($value);
        if (!is_string($value) || strlen($value) > 40) return null;
        $format = preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) ? \DateTimeInterface::ATOM : (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) ? 'Y-m-d H:i:s' : null);
        if (!$format) return null;
        try {
            $date = \DateTimeImmutable::createFromFormat($format, $value, new \DateTimeZone('UTC'));
            return $date && \DateTimeImmutable::getLastErrors() === false ? $date : null;
        } catch (\Throwable) { return null; }
    };
    $draftFields = ['image_adequacy' => ['image_adequacy', 1000], 'observations' => ['observations', 5000], 'limitations' => ['limitations', 3000], 'options' => ['options', 5000], 'recommended_next_step' => ['next_step', 3000]];
@endphp
<div class="clinician-case">
    <section class="clinician-case-hero" aria-labelledby="clinician-case-title">
        <p class="clinician-case-eyebrow">{{ __('clinician_case.eyebrow') }}</p>
        <h2 id="clinician-case-title">{{ __('clinician_case.title') }}</h2>
        <p>{{ __('clinician_case.intro') }}</p>
    </section>
    <nav class="clinician-case-nav" aria-label="{{ __('clinician_case.sections') }}">
        <a href="#clinician-case-documents">{{ __('clinician_case.documents') }}</a>
        @if(empty($isDemo))<a href="#clinician-case-new-draft">{{ __('clinician_case.new_draft') }}</a>@endif
        <a href="#clinician-case-saved-drafts">{{ __('clinician_case.saved_drafts') }}</a>
    </nav>
    <section class="clinician-case-card" id="clinician-case-documents" aria-labelledby="clinician-case-documents-title">
        <h2 id="clinician-case-documents-title">{{ __('clinician_case.documents') }}</h2>
        <p class="clinician-case-note">{{ __('clinician_case.source_help') }}</p>
        <ul class="clinician-case-documents">
            @forelse($documents as $document)
                <li><strong><bdi>{{ $document->original_name }}</bdi></strong>
                    @if(empty($isDemo))
                        <a class="btn" target="_blank" rel="noopener" href="/api/v1/cases/{{ $case->id }}/documents/{{ $document->id }}/content">{{ __('panel_case.open_document') }}</a>
                    @endif
                </li>
            @empty
                <li class="clinician-case-empty">{{ __('clinician_case.no_documents') }}</li>
            @endforelse
        </ul>
    </section>
    @if(empty($isDemo))
        <section class="clinician-case-card" id="clinician-case-new-draft" aria-labelledby="clinician-case-new-title">
            <h2 id="clinician-case-new-title">{{ __('clinician_case.new_draft') }}</h2>
            <p class="clinician-case-note" id="clinician-case-form-help">{{ __('clinician_case.form_help') }}</p>
            <form id="review-form" aria-describedby="clinician-case-form-help">
                <div class="field">
                    <label for="review-document">{{ __('panel_case.review_document') }}</label>
                    <select id="review-document" name="clinical_document_id" required>
                        @foreach($documents as $document)
                            <option value="{{ $document->id }}">{{ $document->original_name }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="source_language" value="{{ $case->source_language }}">
                <div class="clinician-case-form-fields">
                    @foreach($draftFields as $field => [$label, $limit])
                        <div class="field"><label for="review-{{ $field }}">{{ __('panel_case.'.$label) }}</label><textarea id="review-{{ $field }}" name="{{ $field }}" required maxlength="{{ $limit }}"></textarea></div>
                    @endforeach
                </div>
                <button class="btn primary" @disabled($documents->isEmpty())>{{ __('panel_case.save_review') }}</button>
            </form>
            <noscript><p class="clinician-case-note">{{ __('clinician_case.javascript_help') }}</p></noscript>
        </section>
    @endif
    <section class="clinician-case-card" id="clinician-case-saved-drafts" aria-labelledby="clinician-case-saved-title">
        <h2 id="clinician-case-saved-title">{{ __('clinician_case.saved_drafts') }}</h2>
        <p class="clinician-case-note">{{ __('clinician_case.draft_help') }}</p>
        @forelse($draftReviews as $draft)
            @php
                $preview = $draftPreviews->get($draft->id);
                $savedAt = $clinicianDate($preview->created_at ?? $draft->created_at ?? null);
                $sourceLanguage = $preview && in_array($preview->source_language ?? null, ['fa', 'ar', 'en'], true) ? $preview->source_language : null;
            @endphp
            <article class="clinician-case-draft" aria-labelledby="clinician-draft-{{ $draft->id }}">
                <header>
                    <h3 id="clinician-draft-{{ $draft->id }}">{{ __('clinician_case.draft_revision', ['number' => $draft->revision_number]) }}</h3>
                    @if(empty($isDemo))
                        <button class="btn primary" type="button" data-publish-review="{{ $draft->id }}" @disabled(!$preview) aria-describedby="clinician-draft-note-{{ $draft->id }}">{{ __('panel_case.publish') }}</button>
                    @else
                        <p class="clinician-case-note">{{ __('panel.demo_notice') }}</p>
                    @endif
                </header>
                <dl class="clinician-case-draft-meta">
                    <div><dt>{{ __('clinician_case.saved_at') }}</dt><dd>@if($savedAt)<time dir="ltr" datetime="{{ $savedAt->setTimezone(new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM) }}">{{ $savedAt->setTimezone(new \DateTimeZone('Asia/Tehran'))->format('Y-m-d H:i') }}</time>@else{{ __('clinician_case.date_unknown') }}@endif</dd></div>
                    @if($preview)
                        <div><dt>{{ __('clinician_case.source_name') }}</dt><dd><bdi>{{ is_string($preview->source_name ?? null) && trim($preview->source_name) !== '' ? $preview->source_name : __('clinician_case.source_unknown') }}</bdi></dd></div>
                        <div><dt>{{ __('clinician_case.source_language') }}</dt><dd>{{ $sourceLanguage ? __('clinician_case.languages.'.$sourceLanguage) : __('clinician_case.language_unknown') }}</dd></div>
                    @endif
                </dl>
                @if($preview)
                    <dl class="clinician-case-draft-fields">
                        @foreach($draftFields as $field => [$label, $limit])
                            @php $narrative = $preview->{$field} ?? null; @endphp
                            <div data-draft-field="{{ $field }}"><dt>{{ __('panel_case.'.$label) }}</dt><dd @if($sourceLanguage) lang="{{ $sourceLanguage }}" @endif dir="{{ $sourceLanguage ? (in_array($sourceLanguage, ['fa', 'ar'], true) ? 'rtl' : 'ltr') : 'auto' }}">{{ is_string($narrative) && trim($narrative) !== '' ? $narrative : __('clinician_case.not_recorded') }}</dd></div>
                        @endforeach
                    </dl>
                    <p class="clinician-case-note clinician-case-draft-note" id="clinician-draft-note-{{ $draft->id }}">{{ __('clinician_case.publish_help') }}</p>
                @else
                    <p class="clinician-case-empty clinician-case-draft-note" id="clinician-draft-note-{{ $draft->id }}">{{ __('clinician_case.preview_unavailable') }}</p>
                @endif
            </article>
        @empty
            <p class="clinician-case-empty">{{ __('clinician_case.no_drafts') }}</p>
        @endforelse
        <p class="clinician-case-note">{{ __('clinician_case.timezone_hint') }}</p>
    </section>
</div>
@endif
</div>
@endsection
