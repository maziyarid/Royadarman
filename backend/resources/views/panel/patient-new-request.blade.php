<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('request.title') }} · Royadarman</title>
    <style>
        :root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17231f;background:#f5f7f6}*{box-sizing:border-box}body{margin:0}.shell{max-width:820px;margin-inline:auto;padding:24px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-block-end:20px}.card{background:#fff;border:1px solid #e0e6e3;border-radius:18px;padding:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:700;margin-block-end:7px}input,select,textarea{width:100%;border:1px solid #cbd6d1;border-radius:10px;padding:11px 12px;font:inherit;background:#fff}textarea{min-height:110px;resize:vertical}.consent{margin-block:20px;border:1px solid #d8e3de;border-radius:14px;background:#f8faf9;padding:16px}.consent pre{font:inherit;white-space:pre-wrap;line-height:1.75;margin:12px 0;max-height:260px;overflow:auto}.check{display:flex;gap:10px;align-items:flex-start;font-weight:600}.check input{width:auto;margin-block-start:4px}.btn{display:inline-flex;border:1px solid #cbd6d1;background:#fff;color:#173b30;text-decoration:none;padding:10px 15px;border-radius:10px;font:inherit;cursor:pointer}.btn.primary{background:#134437;border-color:#134437;color:#fff}.btn[disabled]{opacity:.55;cursor:not-allowed}.notice{padding:16px;border-radius:12px;background:#eef4f1;margin-block:16px}.notice.error{background:#fff0ef;color:#8b2820}.notice.success{background:#eaf7ef;color:#1d6337}.muted{color:#65736e}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-block-start:18px}@media(max-width:650px){.shell{padding:16px}.grid{grid-template-columns:1fr}.full{grid-column:auto}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <div><strong>Royadarman</strong><div class="muted">{{ __('request.title') }}</div></div>
        <a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('request.back') }}</a>
    </header>

    <section class="card">
        <h1>{{ __('request.title') }}</h1>
        <p class="muted">{{ __('request.intro') }}</p>

        @unless($intakeEnabled)
            <div class="notice">
                <strong>{{ __('request.disabled_title') }}</strong>
                <p>{{ __('request.disabled_text') }}</p>
            </div>
        @else
            <form id="request-form" novalidate>
                <div class="grid">
                    <div>
                        <label for="service_type">{{ __('request.service_type') }}</label>
                        <select id="service_type" required>
                            @foreach(['opg_review','home_dentistry','guidance_referral'] as $service)
                                <option value="{{ $service }}">{{ __('request.services.'.$service) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="name">{{ __('request.name') }}</label>
                        <input id="name" type="text" maxlength="80" autocomplete="name">
                    </div>
                    <div id="area-wrap" hidden>
                        <label for="tehran_area">{{ __('request.tehran_area') }}</label>
                        <select id="tehran_area">
                            <option value=""></option>
                            @foreach($tehranAreas as $area)<option value="{{ $area }}">{{ __('request.areas.'.$area) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="contact_time">{{ __('request.contact_time') }}</label>
                        <select id="contact_time">
                            @foreach(['any','morning','midday','evening','night'] as $time)<option value="{{ $time }}">{{ __('request.times.'.$time) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="budget">{{ __('request.budget') }}</label>
                        <select id="budget" required>
                            @foreach(['economic','balanced','flexible','call'] as $budget)<option value="{{ $budget }}">{{ __('request.budgets.'.$budget) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="full">
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
        @endunless
    </section>
</main>

@if($intakeEnabled)
<script>
(() => {
    const locale = @json($locale);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const form = document.getElementById('request-form');
    const service = document.getElementById('service_type');
    const areaWrap = document.getElementById('area-wrap');
    const area = document.getElementById('tehran_area');
    const accept = document.getElementById('accept');
    const submit = document.getElementById('submit');
    const consentText = document.getElementById('consent-text');
    const message = document.getElementById('message');
    let policy = null;

    const key = () => (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`);
    const headers = (idempotencyKey = null) => {
        const value = {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,'X-Locale':locale};
        if (idempotencyKey) value['Idempotency-Key'] = idempotencyKey;
        return value;
    };
    const show = (text, kind = 'error') => {
        message.className = `notice ${kind}`;
        message.textContent = text;
    };
    const errorText = payload => payload?.error?.message || payload?.error?.code || @json(__('request.error'));

    service.addEventListener('change', () => {
        const home = service.value === 'home_dentistry';
        areaWrap.hidden = !home;
        area.required = home;
        if (!home) area.value = '';
    });

    fetch('/api/v1/policies/case_coordination', {headers:{'Accept':'application/json','X-Locale':locale}})
        .then(async response => {
            const data = await response.json();
            if (!response.ok || !data.data) throw new Error(errorText(data));
            policy = data.data;
            consentText.textContent = policy.content;
            accept.disabled = false;
        })
        .catch(() => {
            consentText.textContent = @json(__('request.consent_unavailable'));
            accept.disabled = true;
            submit.disabled = true;
        });

    accept.addEventListener('change', () => { submit.disabled = !accept.checked || !policy; });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!policy || !accept.checked) return;
        submit.disabled = true;
        submit.textContent = @json(__('request.working'));
        message.className = '';
        message.textContent = '';

        try {
            const draftResponse = await fetch('/api/v1/cases/draft', {
                method:'POST',
                headers:headers(key()),
                body:JSON.stringify({
                    service_type:service.value,
                    name:document.getElementById('name').value || null,
                    tehran_area:area.value || null,
                    preferred_contact_time:document.getElementById('contact_time').value,
                    contact_reason:document.getElementById('reason').value || null,
                    budget_band:document.getElementById('budget').value,
                    budget_input_unit:'toman',
                    source_language:locale
                })
            });
            const draftPayload = await draftResponse.json();
            if (!draftResponse.ok || !draftPayload.data) throw new Error(errorText(draftPayload));

            const draft = draftPayload.data;
            const submitResponse = await fetch(`/api/v1/cases/${encodeURIComponent(draft.id)}/submit`, {
                method:'POST',
                headers:headers(key()),
                body:JSON.stringify({version:draft.version, policy_version:policy.version, content_hash:policy.content_hash})
            });
            const submitPayload = await submitResponse.json();
            if (!submitResponse.ok || !submitPayload.data) throw new Error(errorText(submitPayload));

            show(@json(__('request.success')), 'success');
            window.location.assign(`/${encodeURIComponent(locale)}/panel/cases/${encodeURIComponent(draft.id)}`);
        } catch (error) {
            show(error?.message || @json(__('request.error')));
            submit.disabled = false;
            submit.textContent = @json(__('request.submit'));
        }
    });
})();
</script>
@endif
</body>
</html>
