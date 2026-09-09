<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('ui.admin.title') }} — Royadarman</title>
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
        .btn:hover{border-color:var(--accent)} .btn.primary{background:var(--accent);border-color:var(--accent);color:var(--accent-ink)}
        .btn.danger{border-color:var(--danger);color:var(--danger)} .btn.danger:hover{background:var(--danger);color:#fff}
        .btn.ghost{border-color:transparent} .btn.sm{padding:5px 10px;font-size:13px}
        .card{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:20px;margin:20px 0}
        table{width:100%;border-collapse:collapse} th,td{text-align:start;padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px}
        th{color:var(--muted);font-weight:500;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
        tr:last-child td{border-bottom:none} .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:12px;font-weight:500}
        .badge.published{background:rgba(62,192,125,.15);color:var(--ok)} .badge.draft{background:rgba(154,163,189,.15);color:var(--muted)}
        .badge.in_review{background:rgba(224,169,58,.15);color:var(--warn)} .badge.archived{background:rgba(224,83,58,.12);color:var(--danger)}
        .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:16px}
        .field{display:flex;flex-direction:column;gap:4px} .field label{font-size:12px;color:var(--muted)}
        input,select,textarea{background:var(--bg);border:1px solid var(--line);color:var(--ink);border-radius:8px;padding:8px 10px;font-size:14px;font-family:inherit}
        input:focus,select:focus,textarea:focus{outline:none;border-color:var(--accent)}
        textarea{min-height:280px;resize:vertical;width:100%} input[type=text],input[type=date]{width:100%}
        .grid{display:grid;gap:16px} .grid-2{grid-template-columns:1fr 1fr} @media(max-width:720px){.grid-2{grid-template-columns:1fr}}
        .tabs{display:flex;gap:2px;border-bottom:1px solid var(--line);margin-bottom:16px;flex-wrap:wrap}
        .tabs button{background:none;border:none;color:var(--muted);padding:10px 16px;cursor:pointer;border-bottom:2px solid transparent;font-size:14px}
        .tabs button.active{color:var(--ink);border-bottom-color:var(--accent)} .tab-panel{display:none} .tab-panel.active{display:block}
        .alert{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:14px} .alert.ok{background:rgba(62,192,125,.12);color:var(--ok)}
        .empty{text-align:center;color:var(--muted);padding:40px 20px} .pagination{display:flex;gap:8px;justify-content:center;margin-top:18px}
        .pagination a{padding:6px 12px;border:1px solid var(--line);border-radius:6px;color:var(--muted);text-decoration:none} .pagination a.active{background:var(--accent);color:var(--accent-ink);border-color:var(--accent)}
        .muted{color:var(--muted)} .row-actions{display:flex;gap:8px;flex-wrap:wrap}
    </style>
</head>
<body>
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<header class="topbar">
    <div class="shell">
        <h1>{{ __('ui.admin.title') }}</h1>
        <nav>
            <a href="{{ route('admin.cms.posts.index') }}">{{ __('ui.admin.posts') }}</a>
            <a href="{{ route('admin.cms.categories.index') }}">{{ __('ui.admin.categories') }}</a>
            <a href="{{ route('admin.cms.tags.index') }}">{{ __('ui.admin.tags') }}</a>
            <a href="{{ route('admin.cms.media.index') }}">{{ __('ui.admin.media') }}</a>
            <a href="{{ route('admin.cms.menus.index') }}">{{ __('ui.admin.menus') }}</a>
            <a href="{{ route('admin.cms.redirects.index') }}">{{ __('ui.admin.redirects') }}</a>
            <a href="{{ route('admin.cms.comments.index') }}">{{ __('ui.admin.comments') }}</a>
            <a href="/fa/" rel="noopener" target="_blank">{{ __('ui.admin.view_site') }}</a>
            <form method="POST" action="/api/v1/auth/logout" style="display:inline">@csrf<button class="btn ghost sm" type="submit">{{ __('ui.admin.logout') }}</button></form>
        </nav>
    </div>
</header>
<main id="main" class="shell">
    @if(session('status'))<div class="alert ok" role="status">{{ session('status') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
