@extends('panel.layout')
@section('title', $creating ? __('panel.cms.new_heading') : __('panel.cms.edit_heading'))
@section('heading', $creating ? __('panel.cms.new_heading') : __('panel.cms.edit_heading'))
@section('actions')
    <a class="btn" href="{{ route('marketing.index', ['locale' => $locale]) }}">{{ __('panel.cms.back') }}</a>
@endsection
@section('content')
<p class="hint">{{ __('panel.cms.editor_intro') }}</p>
@if($errors->any())
    <div class="errors" role="alert">
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<section class="card pad">
    <form method="post" action="{{ $creating ? route('marketing.store', ['locale' => $locale]) : route('marketing.update', ['locale' => $locale, 'page' => $page->id]) }}">
        @csrf
        @if(!$creating)
            @method('put')
            <input type="hidden" name="version" value="{{ $page->version }}">
        @endif
        <div class="grid">
            <div class="field">
                <label for="slug">{{ __('panel.cms.slug') }}</label>
                <select id="slug" name="slug" required>
                    @foreach($allowedSlugs as $slug)
                        <option value="{{ $slug }}" @selected(old('slug', $page->slug) === $slug)>{{ $slug }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="page-locale">{{ __('panel.cms.locale') }}</label>
                <select id="page-locale" name="locale" required>
                    @foreach(['fa' => 'فارسی', 'ar' => 'العربية', 'en' => 'English'] as $code => $label)
                        <option value="{{ $code }}" @selected(old('locale', $page->locale) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field full">
                <label for="title">{{ __('panel.cms.page_title') }}</label>
                <input id="title" name="title" maxlength="180" required value="{{ old('title', $page->title) }}">
            </div>
            <div class="field full">
                <label for="excerpt">{{ __('panel.cms.excerpt') }}</label>
                <textarea id="excerpt" name="excerpt" class="textarea-sm" maxlength="1000">{{ old('excerpt', $page->excerpt) }}</textarea>
            </div>
            <div class="field full">
                <label for="body">{{ __('panel.cms.body') }}</label>
                <textarea id="body" name="body" maxlength="50000" required>{{ old('body', $page->body) }}</textarea>
                <p class="hint">{{ __('panel.cms.body_hint') }}</p>
            </div>
            <div class="field">
                <label for="meta_title">{{ __('panel.cms.meta_title') }}</label>
                <input id="meta_title" name="meta_title" maxlength="180" value="{{ old('meta_title', $page->meta_title) }}">
            </div>
            <div class="field">
                <label for="meta_description">{{ __('panel.cms.meta_description') }}</label>
                <textarea id="meta_description" name="meta_description" class="textarea-sm" maxlength="320">{{ old('meta_description', $page->meta_description) }}</textarea>
            </div>
        </div>
        <div class="actions">
            <button class="btn primary" type="submit">{{ $creating ? __('panel.cms.create') : __('panel.cms.save') }}</button>
        </div>
    </form>
    @if(!$creating)
        <hr class="marketing-rule">
        <form method="post" action="{{ route('marketing.publish', ['locale' => $locale, 'page' => $page->id]) }}" data-confirm="{{ __('panel.cms.publish') }}">
            @csrf
            <input type="hidden" name="version" value="{{ $page->version }}">
            <button class="btn publish" type="submit">{{ __('panel.cms.publish') }}</button>
        </form>
    @endif
</section>
@endsection
