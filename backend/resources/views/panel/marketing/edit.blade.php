<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $creating ? 'New page' : 'Edit page' }} · Marketing CMS</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/workspace.css?v=20260913"><script src="/assets/workspace.js?v=20260913" defer></script>
</head>
<body class="workspace-body"><main class="shell">
<header class="top"><div><h1 class="marketing-title">{{ $creating ? 'New marketing page' : 'Edit marketing page' }}</h1><p class="hint">This editor is for public marketing content only. Published edits return to draft until explicitly republished.</p></div><a class="btn" href="{{ route('marketing.index',['locale'=>$locale]) }}">Back</a></header>
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
<div class="field full"><label for="excerpt">Excerpt</label><textarea id="excerpt" name="excerpt" class="textarea-sm" maxlength="1000">{{ old('excerpt',$page->excerpt) }}</textarea></div>
<div class="field full"><label for="body">Body</label><textarea id="body" name="body" maxlength="50000" required>{{ old('body',$page->body) }}</textarea><div class="hint">Plain text is intentionally escaped on the public site. This prevents marketing content from injecting scripts or unsafe HTML.</div></div>
<div class="field"><label for="meta_title">SEO title</label><input id="meta_title" name="meta_title" maxlength="180" value="{{ old('meta_title',$page->meta_title) }}"></div>
<div class="field"><label for="meta_description">SEO description</label><textarea id="meta_description" name="meta_description" class="textarea-sm" maxlength="320">{{ old('meta_description',$page->meta_description) }}</textarea></div>
</div>
<div class="actions"><button class="btn primary" type="submit">{{ $creating ? 'Create draft' : 'Save draft' }}</button></div>
</form>
@if(!$creating)
<hr class="marketing-rule">
<form method="post" action="{{ route('marketing.publish',['locale'=>$locale,'page'=>$page->id]) }}">@csrf<input type="hidden" name="version" value="{{ $page->version }}"><button class="btn publish" type="submit">Publish current version</button></form>
@endif
</section>
</main></body></html>
