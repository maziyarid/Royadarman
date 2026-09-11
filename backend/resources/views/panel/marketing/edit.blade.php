<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $creating ? 'New page' : 'Edit page' }} · Marketing CMS</title>
<style>:root{font-family:system-ui,sans-serif;color:#17231f;background:#f5f7f6}*{box-sizing:border-box}body{margin:0}.shell{max-width:920px;margin:auto;padding:24px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px}.card{background:#fff;border:1px solid #e0e6e3;border-radius:18px;padding:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{display:flex;flex-direction:column;gap:7px;margin-bottom:16px}.field.full{grid-column:1/-1}label{font-weight:650;font-size:.92rem}input,select,textarea{font:inherit;border:1px solid #cbd7d2;border-radius:10px;padding:10px 12px;width:100%;background:#fff}textarea{min-height:240px;resize:vertical;line-height:1.7}.btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #ccd7d2;background:#fff;color:#173b30;text-decoration:none;cursor:pointer;font:inherit}.primary{background:#134437;color:#fff;border-color:#134437}.publish{background:#173b30;color:#fff;border-color:#173b30}.errors{padding:14px;border-radius:10px;background:#fff1f1;color:#8b2828;margin-bottom:18px}.status{padding:12px;border-radius:10px;background:#eef5f1;margin-bottom:18px}.actions{display:flex;gap:10px;flex-wrap:wrap}.hint{font-size:.85rem;color:#65746e;line-height:1.5}@media(max-width:700px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}}</style>
</head>
<body><main class="shell">
<header class="top"><div><h1 style="margin:0">{{ $creating ? 'New marketing page' : 'Edit marketing page' }}</h1><p class="hint">This editor is for public marketing content only. Published edits return to draft until explicitly republished.</p></div><a class="btn" href="{{ route('marketing.index',['locale'=>$locale]) }}">Back</a></header>
@if(session('status'))<div class="status">{{ session('status') === 'published' ? 'Published.' : 'Saved as draft.' }}</div>@endif
@if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<section class="card">
<form method="post" action="{{ $creating ? route('marketing.store',['locale'=>$locale]) : route('marketing.update',['locale'=>$locale,'page'=>$page->id]) }}">
@csrf
@if(!$creating)@method('put')<input type="hidden" name="version" value="{{ $page->version }}">@endif
<div class="grid">
<div class="field"><label for="slug">Public page</label><select id="slug" name="slug" required>@foreach($allowedSlugs as $slug)<option value="{{ $slug }}" @selected(old('slug',$page->slug)===$slug)>{{ $slug }}</option>@endforeach</select></div>
<div class="field"><label for="locale">Locale</label><select id="locale" name="locale" required>@foreach(['fa','ar','en'] as $l)<option value="{{ $l }}" @selected(old('locale',$page->locale)===$l)>{{ $l }}</option>@endforeach</select></div>
<div class="field full"><label for="title">Title</label><input id="title" name="title" maxlength="180" required value="{{ old('title',$page->title) }}"></div>
<div class="field full"><label for="excerpt">Excerpt</label><textarea id="excerpt" name="excerpt" style="min-height:100px" maxlength="1000">{{ old('excerpt',$page->excerpt) }}</textarea></div>
<div class="field full"><label for="body">Body</label><textarea id="body" name="body" maxlength="50000" required>{{ old('body',$page->body) }}</textarea><div class="hint">Plain text is intentionally escaped on the public site. This prevents marketing content from injecting scripts or unsafe HTML.</div></div>
<div class="field"><label for="meta_title">SEO title</label><input id="meta_title" name="meta_title" maxlength="180" value="{{ old('meta_title',$page->meta_title) }}"></div>
<div class="field"><label for="meta_description">SEO description</label><textarea id="meta_description" name="meta_description" style="min-height:100px" maxlength="320">{{ old('meta_description',$page->meta_description) }}</textarea></div>
</div>
<div class="actions"><button class="btn primary" type="submit">{{ $creating ? 'Create draft' : 'Save draft' }}</button></div>
</form>
@if(!$creating)
<hr style="border:0;border-top:1px solid #edf1ef;margin:24px 0">
<form method="post" action="{{ route('marketing.publish',['locale'=>$locale,'page'=>$page->id]) }}">@csrf<input type="hidden" name="version" value="{{ $page->version }}"><button class="btn publish" type="submit">Publish current version</button></form>
@endif
</section>
</main></body></html>
