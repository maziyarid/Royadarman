<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>Marketing CMS · Royadarman</title>
    <style>:root{font-family:system-ui,sans-serif;color:#17231f;background:#f5f7f6}*{box-sizing:border-box}body{margin:0}.shell{max-width:1100px;margin:auto;padding:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:24px}.btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ccd7d2;background:#fff;color:#173b30;text-decoration:none}.primary{background:#134437;color:#fff;border-color:#134437}.card{background:#fff;border:1px solid #e0e6e3;border-radius:18px;overflow:hidden}table{border-collapse:collapse;width:100%}th,td{padding:13px 15px;text-align:start;border-bottom:1px solid #edf1ef}th{background:#fafbfa}.badge{padding:4px 8px;border-radius:999px;background:#edf4f0}.empty{padding:30px;text-align:center;color:#65746e}.hint{color:#65746e;line-height:1.7;margin-bottom:20px}</style>
</head>
<body><main class="shell">
<header class="top"><div><h1 style="margin:0">Marketing CMS</h1><div class="hint">Public marketing content only. Consent, privacy and clinical text are not editable here.</div></div><div><a class="btn" href="{{ route('panel', ['locale'=>$locale]) }}">Panel</a> <a class="btn primary" href="{{ route('marketing.create', ['locale'=>$locale]) }}">New page</a></div></header>
<section class="card">
@if($pages->isEmpty())<div class="empty">No CMS pages yet. Public pages currently use the safe multilingual fallback copy.</div>@else
<table><thead><tr><th>Slug</th><th>Locale</th><th>Title</th><th>Status</th><th>Version</th><th></th></tr></thead><tbody>
@foreach($pages as $page)<tr><td><bdi>{{ $page->slug }}</bdi></td><td>{{ $page->locale }}</td><td>{{ $page->title }}</td><td><span class="badge">{{ $page->status }}</span></td><td>{{ $page->version }}</td><td><a class="btn" href="{{ route('marketing.edit',['locale'=>$locale,'page'=>$page->id]) }}">Edit</a></td></tr>@endforeach
</tbody></table>@endif
</section>
</main></body></html>
