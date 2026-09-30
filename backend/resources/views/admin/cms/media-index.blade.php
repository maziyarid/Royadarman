@extends('admin.layout')
@section('content')
<div class="heading-row">
    <div>
        <h2>{{ __('ui.admin.media') }}</h2>
        <p class="muted">{{ __('ui.admin.media_help') }}</p>
    </div>
</div>

<div class="card">
    <form method="POST" action="{{ route('admin.cms.media.store') }}" enctype="multipart/form-data" class="filters">
        @csrf
        <div class="field">
            <label>{{ __('ui.admin.filename') }}</label>
            <input type="file" name="file" accept="image/jpeg,image/png,image/webp" required>
        </div>
        <button class="btn primary" type="submit">{{ __('ui.admin.upload') }}</button>
    </form>
</div>

<form class="filters" method="GET">
    <div class="field">
        <label>{{ __('ui.admin.search') }}</label>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.admin.media_search_placeholder') }}">
    </div>
    <button class="btn" type="submit">{{ __('ui.admin.search') }}</button>
</form>

@if($media->isEmpty())
    <div class="card"><div class="empty">{{ __('ui.admin.empty') }}</div></div>
@else
    <div class="media-admin-grid">
        @foreach($media as $m)
            @php
                $current = $m->translations->firstWhere('locale', app()->getLocale()) ?? $m->translations->first();
            @endphp
            <article class="card media-admin-card">
                <a href="{{ route('cms.media.serve', $m) }}" target="_blank" rel="noopener" class="media-preview">
                    <img src="{{ route('cms.media.serve', $m) }}" alt="{{ $current?->alt_text ?? '' }}" loading="lazy">
                </a>
                <div class="media-admin-meta">
                    <strong>{{ $m->original_filename }}</strong>
                    <span class="muted">
                        @if($m->width && $m->height){{ $m->width }}×{{ $m->height }} · @endif
                        {{ number_format($m->byte_size / 1024, 1) }} KB
                    </span>
                </div>

                <details>
                    <summary>{{ __('ui.admin.edit_metadata') }}</summary>
                    <form method="POST" action="{{ route('admin.cms.media.update', $m) }}" class="table-spaced">
                        @csrf
                        @method('PATCH')
                        <div class="tabs" role="tablist">
                            @foreach(['fa','ar','en'] as $locale)
                                <span class="badge">{{ strtoupper($locale) }}</span>
                            @endforeach
                        </div>
                        @foreach(['fa','ar','en'] as $locale)
                            @php $tr=$m->translations->firstWhere('locale',$locale); @endphp
                            <fieldset class="metadata-fieldset" dir="{{ $locale === 'en' ? 'ltr' : 'rtl' }}">
                                <legend>{{ strtoupper($locale) }}</legend>
                                <input type="hidden" name="translations[{{ $locale }}][locale]" value="{{ $locale }}">
                                <div class="field">
                                    <label>{{ __('ui.admin.alt') }}</label>
                                    <input name="translations[{{ $locale }}][alt_text]" value="{{ $tr?->alt_text }}" maxlength="300">
                                </div>
                                <div class="field">
                                    <label>{{ __('ui.admin.caption') }}</label>
                                    <input name="translations[{{ $locale }}][caption]" value="{{ $tr?->caption }}" maxlength="500">
                                </div>
                                <div class="field">
                                    <label>{{ __('ui.admin.description') }}</label>
                                    <textarea class="textarea-sm" name="translations[{{ $locale }}][description]" maxlength="2000">{{ $tr?->description }}</textarea>
                                </div>
                            </fieldset>
                        @endforeach
                        <button class="btn primary" type="submit">{{ __('ui.admin.save_metadata') }}</button>
                    </form>
                </details>

                <form method="POST" action="{{ route('admin.cms.media.destroy', $m) }}" class="form-spaced">
                    @csrf
                    @method('DELETE')
                    <button class="btn sm danger" type="submit" data-confirm="{{ __('ui.admin.delete_confirm') }}">{{ __('ui.admin.delete') }}</button>
                </form>
            </article>
        @endforeach
    </div>
@endif

{{ $media->links() }}
@endsection
