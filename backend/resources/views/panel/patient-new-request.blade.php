@extends('panel.layout')
@section('title', __('request.title'))
@section('heading', __('request.title'))
@section('actions')
    <a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('request.back') }}</a>
@endsection
@push('scripts')
    <script src="/assets/patient-request.js?v=20260915" defer></script>
@endpush
@section('content')
<div data-patient-request data-locale="{{ $locale }}" data-error="{{ __('request.error') }}" data-consent-unavailable="{{ __('request.consent_unavailable') }}" data-working="{{ __('request.working') }}" data-success="{{ __('request.success') }}" data-submit="{{ __('request.submit') }}">
    <p class="hint">{{ __('request.intro') }}</p>
    @unless($intakeEnabled)
        <div class="notice">
            <strong>{{ __('request.disabled_title') }}</strong>
            <p>{{ __('request.disabled_text') }}</p>
        </div>
    @else
        <section class="card pad">
            <form id="request-form" novalidate>
                <div class="grid">
                    <div class="field">
                        <label for="service_type">{{ __('request.service_type') }}</label>
                        <select id="service_type" required>
                            @foreach(['opg_review','home_dentistry','guidance_referral'] as $service)
                                <option value="{{ $service }}">{{ __('request.services.'.$service) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="name">{{ __('request.name') }}</label>
                        <input id="name" type="text" maxlength="80" autocomplete="name">
                    </div>
                    <div class="field" id="area-wrap" hidden>
                        <label for="tehran_area">{{ __('request.tehran_area') }}</label>
                        <select id="tehran_area">
                            <option value=""></option>
                            @foreach($tehranAreas as $area)<option value="{{ $area }}">{{ __('request.areas.'.$area) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="contact_time">{{ __('request.contact_time') }}</label>
                        <select id="contact_time">
                            @foreach(['any','morning','midday','evening','night'] as $time)<option value="{{ $time }}">{{ __('request.times.'.$time) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="budget">{{ __('request.budget') }}</label>
                        <select id="budget" required>
                            @foreach(['economic','balanced','flexible','call'] as $budget)<option value="{{ $budget }}">{{ __('request.budgets.'.$budget) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field full">
                        <label for="reason">{{ __('request.reason') }}</label>
                        <textarea id="reason" maxlength="1000" dir="auto"></textarea>
                    </div>
                </div>
                <section class="consent" aria-labelledby="consent-title">
                    <strong id="consent-title">{{ __('request.consent_title') }}</strong>
                    <pre id="consent-text">{{ __('request.consent_loading') }}</pre>
                    <label class="check"><input id="accept" type="checkbox" disabled required><span>{{ __('request.accept') }}</span></label>
                </section>
                <div id="message" role="status" aria-live="polite"></div>
                <div class="actions">
                    <button id="submit" class="btn primary" type="submit" disabled>{{ __('request.submit') }}</button>
                    <a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('request.back') }}</a>
                </div>
            </form>
        </section>
    @endunless
</div>
@endsection
