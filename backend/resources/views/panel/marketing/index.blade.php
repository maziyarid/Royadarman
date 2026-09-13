<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Marketing CMS · Royadarman</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/workspace.css?v=20260913"><script src="/assets/workspace.js?v=20260913" defer></script>
</head>
<body class="workspace-body"><main class="shell">
<header class="top"><div><h1 class="marketing-title">Marketing CMS</h1><div class="hint">Public marketing content only. Consent, privacy and clinical text are not editable here.</div></div><div><a class="btn" href="{{ route('panel', ['locale'=>$locale]) }}">Panel</a> <a class="btn primary" href="{{ route('marketing.create', ['locale'=>$locale]) }}">New page</a></div></header>
<section class="card">
@if($pages->isEmpty())<div class="empty">No CMS pages yet. Public pages currently use the safe multilingual fallback copy.</div>@else
<table><thead><tr><th>Slug</th><th>Locale</th><th>Title</th><th>Status</th><th>Version</th><th></th></tr></thead><tbody>
@foreach($pages as $page)<tr><td><bdi>{{ $page->slug }}</bdi></td><td>{{ $page->locale }}</td><td>{{ $page->title }}</td><td><span class="badge">{{ $page->status }}</span></td><td>{{ $page->version }}</td><td><a class="btn" href="{{ route('marketing.edit',['locale'=>$locale,'page'=>$page->id]) }}">Edit</a></td></tr>@endforeach
</tbody></table>@endif
</section>
</main></body></html>
