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
    @if(empty($isDemo) && $statusValue === 'draft')
        <section class="card pad">
            <h2>{{ __('panel_case.submit') }}</h2>
            <p class="muted">{{ __('panel_case.submit_help') }}</p>
            <button class="btn primary" type="button" data-submit-case>{{ __('panel_case.submit') }}</button>
        </section>
    @endif
    @if(empty($isDemo))
        <section class="card pad">
            <h2>{{ __('panel_case.upload') }}</h2>
            <form id="upload-form">
                <div class="field">
                    <label for="opg-file">{{ __('panel_case.file') }}</label>
                    <input id="opg-file" name="document" type="file" accept="image/jpeg,image/png" required>
                </div>
                <button class="btn primary" type="submit">{{ __('panel_case.upload') }}</button>
            </form>
        </section>
    @endif
    <section class="card pad">
        <h2>{{ __('panel_case.documents') }}</h2>
        @forelse($documents as $document)
            <div class="item">
                <bdi>{{ $document->original_name }}</bdi> · {{ __('panel_case.doc_status.'.$document->status) }}
                @if(empty($isDemo) && $document->status === 'approved')
                    <a class="btn" target="_blank" rel="noopener" href="/api/v1/cases/{{ $case->id }}/documents/{{ $document->id }}/content">{{ __('panel_case.open_document') }}</a>
                @endif
            </div>
        @empty
            <p class="muted">{{ __('panel_case.no_items') }}</p>
        @endforelse
    </section>
    <section class="card pad">
        <h2>{{ __('panel_case.referrals') }}</h2>
        @forelse($referrals as $referral)
            <div class="item">
                <strong>{{ $referral->clinic_name }}</strong> · {{ __('panel_case.referral_status.'.$referral->status) }}
                @if(empty($isDemo) && $referral->status === 'proposed')
                    <div class="actions">
                        <button class="btn primary" type="button" data-referral-decision="accepted" data-referral-id="{{ $referral->id }}" data-language="{{ $referral->source_language }}">{{ __('panel_case.accept') }}</button>
                        <button class="btn danger" type="button" data-referral-decision="declined" data-referral-id="{{ $referral->id }}" data-language="{{ $referral->source_language }}">{{ __('panel_case.decline') }}</button>
                    </div>
                @endif
            </div>
        @empty
            <p class="muted">{{ __('panel_case.no_items') }}</p>
        @endforelse
    </section>
    <section class="card pad">
        <h2>{{ __('panel_case.reviews') }}</h2>
        @forelse($reviews as $review)
            <article class="item">
                <strong>#{{ $review->revision_number }}</strong>
                <p class="review-text"><b>{{ __('panel_case.image_adequacy') }}:</b> {{ $review->image_adequacy }}

<b>{{ __('panel_case.observations') }}:</b> {{ $review->observations }}

<b>{{ __('panel_case.limitations') }}:</b> {{ $review->limitations }}

<b>{{ __('panel_case.options') }}:</b> {{ $review->options }}

<b>{{ __('panel_case.next_step') }}:</b> {{ $review->recommended_next_step }}</p>
            </article>
        @empty
            <p class="muted">{{ __('panel_case.no_items') }}</p>
        @endforelse
    </section>
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
<section class="card pad">
    <h2>{{ __('panel_case.documents') }}</h2>
    @forelse($documents as $document)
        <div class="item">
            <bdi>{{ $document->original_name }}</bdi>
            @if(empty($isDemo))
                <a class="btn" target="_blank" rel="noopener" href="/api/v1/cases/{{ $case->id }}/documents/{{ $document->id }}/content">{{ __('panel_case.open_document') }}</a>
            @endif
        </div>
    @empty
        <p class="muted">{{ __('panel_case.no_items') }}</p>
    @endforelse
</section>
@if(empty($isDemo))
    <section class="card pad">
        <h2>{{ __('panel_case.clinician_actions') }}</h2>
        <form id="review-form">
            <div class="field">
                <label>{{ __('panel_case.review_document') }}</label>
                <select name="clinical_document_id" required>
                    @foreach($documents as $document)
                        <option value="{{ $document->id }}">{{ $document->original_name }}</option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="source_language" value="{{ $case->source_language }}">
            <div class="field"><label>{{ __('panel_case.image_adequacy') }}</label><textarea name="image_adequacy" required maxlength="1000"></textarea></div>
            <div class="field"><label>{{ __('panel_case.observations') }}</label><textarea name="observations" required maxlength="5000"></textarea></div>
            <div class="field"><label>{{ __('panel_case.limitations') }}</label><textarea name="limitations" required maxlength="3000"></textarea></div>
            <div class="field"><label>{{ __('panel_case.options') }}</label><textarea name="options" required maxlength="5000"></textarea></div>
            <div class="field"><label>{{ __('panel_case.next_step') }}</label><textarea name="recommended_next_step" required maxlength="3000"></textarea></div>
            <button class="btn primary" @disabled($documents->isEmpty())>{{ __('panel_case.save_review') }}</button>
        </form>
        <h3>{{ __('panel_case.drafts') }}</h3>
        @forelse($draftReviews as $draft)
            <div class="item">#{{ $draft->revision_number }} · <button class="btn primary" type="button" data-publish-review="{{ $draft->id }}">{{ __('panel_case.publish') }}</button></div>
        @empty
            <p class="muted">{{ __('panel_case.no_items') }}</p>
        @endforelse
    </section>
@else
    <section class="card pad">
        <h2>{{ __('panel_case.reviews') }}</h2>
        @forelse($draftReviews as $draft)
            <div class="item">#{{ $draft->revision_number }} · {{ __('panel.demo_notice') }}</div>
        @empty
            <p class="muted">{{ __('panel_case.no_items') }}</p>
        @endforelse
    </section>
@endif
@endif
</div>
@endsection
