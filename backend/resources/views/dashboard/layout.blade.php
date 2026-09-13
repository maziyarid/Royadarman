<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('ui.dashboard.title') }} — Royadarman</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <style>
        :root { --bg:#0f1424; --panel:#1a2138; --ink:#e6e9f2; --muted:#9aa3bd; --accent:#4f7cff; --accent-ink:#fff; --line:#2a3354; --ok:#3ec07d; --warn:#e0a93a; --danger:#e0533a; }
        *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:1.5}
        .skip-link{position:absolute;left:-9999px} .skip-link:focus{left:8px;top:8px;z-index:99;background:var(--accent);color:var(--accent-ink);padding:8px 12px;border-radius:6px}
        .shell{max-width:1180px;margin:0 auto;padding:0 20px}
        .topbar{background:var(--panel);border-bottom:1px solid var(--line);padding:14px 0;position:sticky;top:0;z-index:10}
        .topbar .shell{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .topbar h1{font-size:16px;margin:0;font-weight:600} .topbar nav{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
        .topbar a{color:var(--muted);text-decoration:none;font-size:14px} .topbar a:hover{color:var(--ink)}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid var(--line);background:var(--panel);color:var(--ink);font-size:14px;cursor:pointer;text-decoration:none}
        .btn:hover{border-color:var(--accent)} .btn.ghost{border-color:transparent}
        .card{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:20px;margin:18px 0}
        .card h2{font-size:15px;margin:0 0 14px;font-weight:600}
        .stat-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr))}
        .stat{background:var(--bg);border:1px solid var(--line);border-radius:10px;padding:14px}
        .stat .num{font-size:28px;font-weight:700} .stat .label{color:var(--muted);font-size:13px;margin-top:2px}
        table{width:100%;border-collapse:collapse} th,td{text-align:start;padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px}
        th{color:var(--muted);font-weight:500;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
        tr:last-child td{border-bottom:none}
        .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:12px;font-weight:500}
        .badge.submitted{background:rgba(154,163,189,.15);color:var(--muted)} .badge.in_review{background:rgba(224,169,58,.15);color:var(--warn)}
        .badge.awaiting_patient{background:rgba(79,124,255,.15);color:var(--accent)} .badge.completed{background:rgba(62,192,125,.15);color:var(--ok)}
        .badge.cancelled{background:rgba(224,83,58,.12);color:var(--danger)} .badge.published{background:rgba(62,192,125,.15);color:var(--ok)}
        .badge.draft{background:rgba(154,163,189,.15);color:var(--muted)} .badge.proposed{background:rgba(79,124,255,.15);color:var(--accent)}
        .badge.requested{background:rgba(154,163,189,.15);color:var(--muted)} .badge.area_verified{background:rgba(79,124,255,.15);color:var(--accent)}
        .badge.coordinator_review{background:rgba(224,169,58,.15);color:var(--warn)} .badge.open{background:rgba(224,83,58,.12);color:var(--danger)}
        .badge.resolved{background:rgba(62,192,125,.15);color:var(--ok)} .badge.verified{background:rgba(62,192,125,.15);color:var(--ok)}
        .badge.unverified{background:rgba(224,83,58,.12);color:var(--danger)}
        .empty{text-align:center;color:var(--muted);padding:32px 20px}
        .muted{color:var(--muted)} .ok{color:var(--ok)} .warn{color:var(--warn)} .danger{color:var(--danger)}
        .two-col{display:grid;gap:16px} .two-col{grid-template-columns:1fr 1fr} @media(max-width:720px){.two-col{grid-template-columns:1fr}}
    </style>
</head>
<body>
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="topbar">
    <div class="shell">
        <h1>{{ __('ui.dashboard.title') }} · {{ __('ui.dashboard.roles.'.$data['role']) }}</h1>
        <nav>
            <a href="/fa/" rel="noopener" target="_blank">{{ __('ui.admin.view_site') }}</a>
            <form method="POST" action="/api/v1/auth/logout" style="display:inline">@csrf<button class="btn ghost" type="submit">{{ __('ui.admin.logout') }}</button></form>
        </nav>
    </div>
</header>
<main id="main" class="shell">
    @yield('content')
</main>
</body>
</html>
