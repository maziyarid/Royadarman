<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('panel.roles.'.$panelKey.'.title') }} · Royadarman</title>
    <style>
        :root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17231f;background:#f5f7f6}
        *{box-sizing:border-box}body{margin:0}.shell{max-width:1180px;margin-inline:auto;padding:24px}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-block-end:28px}.brand{font-weight:800}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.btn{display:inline-flex;border:1px solid #ccd7d2;background:#fff;color:#173b30;text-decoration:none;padding:9px 13px;border-radius:10px;font:inherit;cursor:pointer}.btn.primary{background:#134437;color:#fff;border-color:#134437}.hero{background:#fff;border:1px solid #e0e6e3;border-radius:18px;padding:24px;margin-block-end:20px}.hero h1{margin:0 0 8px;font-size:clamp(1.45rem,3vw,2rem)}.hero p{margin:0;color:#5a6963;max-width:760px}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-block:18px}.metric{background:#fff;border:1px solid #e0e6e3;border-radius:14px;padding:16px}.metric strong{display:block;font-size:1.6rem;margin-block-end:4px}.metric span{color:#63736c;font-size:.9rem}.card{background:#fff;border:1px solid #e0e6e3;border-radius:18px;overflow:hidden}.card-head{padding:18px 20px;border-block-end:1px solid #edf1ef;font-weight:700}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px 16px;text-align:start;border-block-end:1px solid #edf1ef;white-space:nowrap}th{font-size:.82rem;color:#65736e;background:#fafbfa}.empty{padding:28px;color:#66756f;text-align:center}.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef4f1;font-size:.82rem}.notice{margin-block-start:18px;padding:13px 16px;background:#eef4f1;border-radius:12px;color:#365348;font-size:.9rem}.case-link{font-weight:700;color:#134437;text-decoration:none}.case-link:hover{text-decoration:underline}@media(max-width:640px){.shell{padding:16px}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<main class="shell">
    <header class="top">
        <div class="brand">{{ __('panel.brand') }}</div>
        <div class="actions">
            @if($panelKey === 'patient')
                <a class="btn primary" href="{{ route('patient.request.create', ['locale' => app()->getLocale()]) }}">{{ __('request.title') }}</a>
            @endif
            @if($canManageMarketing)
                <a class="btn primary" href="{{ route('marketing.index', ['locale' => app()->getLocale()]) }}">{{ __('panel.marketing') }}</a>
            @endif
            <a class="btn" href="{{ route('public.home', ['locale' => app()->getLocale()]) }}">{{ __('panel.back_home') }}</a>
            <button id="logout" class="btn" type="button">{{ __('panel.logout') }}</button>
        </div>
    </header>

    <section class="hero">
        <h1>{{ __('panel.roles.'.$panelKey.'.title') }}</h1>
        <p>{{ __('panel.roles.'.$panelKey.'.subtitle') }}</p>
    </section>

    <section class="metrics" aria-label="Summary">
        @foreach($metrics as $key => $value)
            <article class="metric"><strong>{{ number_format((int) $value) }}</strong><span>{{ __('panel.metrics.'.$key) }}</span></article>
        @endforeach
    </section>

    @if($cases->isNotEmpty())
        <section class="card">
            <div class="card-head">{{ __('panel.roles.'.$panelKey.'.title') }}</div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('panel.table.reference') }}</th><th>{{ __('panel.table.service') }}</th><th>{{ __('panel.table.status') }}</th><th>{{ __('panel.table.updated') }}</th></tr></thead>
                    <tbody>
                    @foreach($cases as $case)
                        @php
                            $service = $case->service_type instanceof \BackedEnum ? $case->service_type->value : $case->service_type;
                            $status = $case->status instanceof \BackedEnum ? $case->status->value : $case->status;
                        @endphp
                        <tr>
                            <td><a class="case-link" href="{{ route('panel.case', ['locale' => app()->getLocale(), 'case' => $case->id]) }}"><bdi>{{ $case->public_reference }}</bdi></a></td>
                            <td><span class="badge">{{ $service }}</span></td>
                            <td>{{ $status }}</td>
                            <td><bdi>{{ $case->updated_at }}</bdi></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="card"><div class="empty">{{ __('panel.empty') }}</div></section>
    @endif

    @if(in_array($panelKey, ['owner','tech_admin'], true))
        <p class="notice">{{ __('panel.roles.'.$panelKey.'.subtitle') }}</p>
    @endif
</main>
<script>
document.getElementById('logout').addEventListener('click', async () => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const response = await fetch('/api/v1/auth/logout', {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Locale':@json(app()->getLocale())}});
    if (response.ok) window.location.assign(@json(route('public.home', ['locale' => app()->getLocale()])));
});
</script>
</body>
</html>
