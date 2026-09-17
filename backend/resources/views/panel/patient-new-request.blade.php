@extends('panel.layout')
@section('title', __('request.title'))
@section('heading', __('request.title'))
@section('actions')
    <a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('request.back') }}</a>
@endsection
@push('scripts')
    <script src="/assets/patient-request.js?v=20260916b" defer></script>
@endpush
@section('content')
<div data-patient-request data-locale="{{ $locale }}" data-error="{{ __('request.error') }}" data-consent-unavailable="{{ __('request.consent_unavailable') }}" data-working="{{ __('request.working') }}" data-success="{{ __('request.success') }}" data-submit="{{ __('request.submit') }}" data-next="{{ __('request.next') }}" data-prev="{{ __('request.prev') }}" data-step-of="{{ __('request.step_of') }}">
    <p class="hint">{{ __('request.intro') }}</p>
    @unless($intakeEnabled)
        <div class="notice"><strong>{{ __('request.disabled_title') }}</strong><p>{{ __('request.disabled_text') }}</p></div>
    @else
        <section class="card pad request-wizard">
            <div class="request-progress" role="progressbar" aria-valuemin="1" aria-valuemax="8" aria-valuenow="1" data-progress>
                <div class="request-progress-bar" data-progress-bar style="width:12.5%"></div>
                <span class="request-progress-label" data-progress-label>{{ __('request.step_of', ['current' => 1, 'total' => 8]) }}</span>
            </div>
            <form id="request-form" novalidate>
                <div class="request-step" data-step="1"><h2 class="request-step-title">{{ __('request.steps.service') }}</h2><p class="hint">{{ __('request.service_help') }}</p><div class="request-choice-grid" role="radiogroup">@foreach(['guidance_referral','opg_review','home_dentistry'] as $service)<label class="request-choice"><input type="radio" name="service_type" value="{{ $service }}" required @checked($loop->first)><span><strong>{{ __('request.services.'.$service) }}</strong><small>{{ __('request.service_hints.'.$service) }}</small></span></label>@endforeach</div></div>
                <div class="request-step" data-step="2" hidden><h2 class="request-step-title">{{ __('request.steps.urgency') }}</h2><p class="hint">{{ __('request.urgency_help') }}</p><div class="request-choice-grid" role="radiogroup">@foreach(['normal'=>'routine','urgent'=>'urgent'] as $value=>$key)<label class="request-choice"><input type="radio" name="priority" value="{{ $value }}" required @checked($value==='normal')><span><strong>{{ __('request.urgencies.'.$key) }}</strong><small>{{ __('request.urgency_hints.'.$key) }}</small></span></label>@endforeach</div></div>
                <div class="request-step" data-step="3" hidden><h2 class="request-step-title">{{ __('request.steps.location') }}</h2><p class="hint">{{ __('request.location_help') }}</p><div class="field"><label for="tehran_area">{{ __('request.tehran_area') }}</label><select id="tehran_area" name="tehran_area"><option value="">{{ __('request.area_optional') }}</option>@foreach($tehranAreas as $area)<option value="{{ $area }}">{{ __('request.areas.'.$area) }}</option>@endforeach</select></div><p class="hint" data-home-area-note hidden>{{ __('request.home_area_required') }}</p></div>
                <div class="request-step" data-step="4" hidden><h2 class="request-step-title">{{ __('request.steps.preferences') }}</h2><div class="grid"><div class="field"><label for="contact_time">{{ __('request.contact_time') }}</label><select id="contact_time">@foreach(['any','morning','midday','evening','night'] as $time)<option value="{{ $time }}">{{ __('request.times.'.$time) }}</option>@endforeach</select></div><div class="field"><label for="budget">{{ __('request.budget') }}</label><select id="budget" required>@foreach(['balanced','economic','flexible','call'] as $budget)<option value="{{ $budget }}">{{ __('request.budgets.'.$budget) }}</option>@endforeach</select></div></div></div>
                <div class="request-step" data-step="5" hidden><h2 class="request-step-title">{{ __('request.steps.details') }}</h2><div class="grid"><div class="field"><label for="name">{{ __('request.name') }}</label><input id="name" type="text" maxlength="80" autocomplete="name"></div><div class="field full"><label for="reason">{{ __('request.reason') }}</label><textarea id="reason" maxlength="1000" dir="auto" rows="4"></textarea></div></div></div>
                <div class="request-step" data-step="6" hidden><h2 class="request-step-title">{{ __('request.steps.documents') }}</h2><div class="notice"><p>{{ __('request.documents_note') }}</p><p>{{ __('request.documents_no_diagnosis') }}</p></div></div>
                <div class="request-step" data-step="7" hidden><h2 class="request-step-title">{{ __('request.steps.consent') }}</h2><section class="consent" aria-labelledby="consent-title"><strong id="consent-title">{{ __('request.consent_title') }}</strong><pre id="consent-text">{{ __('request.consent_loading') }}</pre><label class="check"><input id="accept" type="checkbox" disabled required><span>{{ __('request.accept') }}</span></label></section></div>
                <div class="request-step" data-step="8" hidden><h2 class="request-step-title">{{ __('request.steps.review') }}</h2><p class="hint">{{ __('request.review_help') }}</p><dl class="request-summary" data-summary><div><dt>{{ __('request.service_type') }}</dt><dd data-sum="service">—</dd></div><div><dt>{{ __('request.urgency') }}</dt><dd data-sum="priority">—</dd></div><div><dt>{{ __('request.tehran_area') }}</dt><dd data-sum="area">—</dd></div><div><dt>{{ __('request.contact_time') }}</dt><dd data-sum="time">—</dd></div><div><dt>{{ __('request.budget') }}</dt><dd data-sum="budget">—</dd></div><div><dt>{{ __('request.name') }}</dt><dd data-sum="name">—</dd></div><div class="full"><dt>{{ __('request.reason') }}</dt><dd data-sum="reason">—</dd></div></dl></div>
                <div id="message" role="status" aria-live="polite"></div>
                <div class="actions request-nav"><button type="button" class="btn" data-prev hidden>{{ __('request.prev') }}</button><button type="button" class="btn primary" data-next>{{ __('request.next') }}</button><button id="submit" class="btn primary" type="submit" hidden disabled>{{ __('request.submit') }}</button><a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('request.back') }}</a></div>
            </form>
        </section>
    @endunless
</div>
<style>
.request-wizard{display:grid;gap:20px}.request-progress{display:grid;gap:8px}.request-progress-bar{height:6px;border-radius:999px;background:linear-gradient(90deg,#2947A3,#162B70);transition:width .2s ease}.request-progress-label{font-size:13px;color:#5b6475}.request-step-title{margin:0 0 8px;font-size:1.25rem;color:#162B70}.request-choice-grid{display:grid;gap:12px}@media(min-width:720px){.request-choice-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}.request-choice{display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid #d7dce8;border-radius:14px;background:#fff;cursor:pointer}.request-choice:has(input:checked){border-color:#2947A3;box-shadow:0 0 0 2px rgba(41,71,163,.15);background:#F7F5EF}.request-choice input{margin-top:3px}.request-choice strong{display:block;color:#162B70}.request-choice small{display:block;color:#5b6475;margin-top:4px;line-height:1.4}.request-summary{display:grid;gap:10px;margin:0}.request-summary>div{display:grid;gap:2px;padding:10px 12px;border-radius:12px;background:#F7F5EF}.request-summary dt{font-size:12px;color:#5b6475}.request-summary dd{margin:0;color:#162B70;font-weight:600;white-space:pre-wrap}.request-nav{flex-wrap:wrap}
</style>
@endsection
