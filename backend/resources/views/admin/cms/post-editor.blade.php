@extends('admin.layout')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin:20px 0">
    <h2 style="margin:0;font-size:20px">{{ $post->exists ? __('ui.admin.edit') : __('ui.admin.new_post') }}</h2>
    @if($post->exists)
        <a href="{{ route('admin.cms.posts.index') }}" class="btn ghost">{{ __('ui.admin.posts') }}</a>
    @endif
</div>

<form method="POST" action="{{ $post->exists ? route('admin.cms.posts.update', $post) : route('admin.cms.posts.store') }}">
    @csrf
    @if($post->exists)@method('PATCH')@endif

    <div class="card">
        <div class="grid grid-2">
            <div class="field">
                <label>{{ __('ui.admin.type') }}</label>
                <select name="type" required>
                    @foreach($types as $t)<option value="{{ $t->value }}" @selected($post->type?->value === $t->value)>{{ $t->value }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ __('ui.admin.status') }}</label>
                <select name="status" required>
                    @foreach($statuses as $s)<option value="{{ $s->value }}" @selected($post->status?->value === $s->value)>{{ $s->value }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>{{ __('ui.admin.published_at') }}</label>
                <input type="date" name="published_at" value="{{ $post->published_at?->format('Y-m-d') }}">
            </div>
            <div class="field" style="flex-direction:row;align-items:center;gap:8px;padding-top:22px">
                <input type="checkbox" name="is_featured" value="1" id="featured" @checked($post->is_featured)>
                <label for="featured" style="font-size:14px;color:var(--ink)">{{ __('ui.admin.featured') }}</label>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="tabs" role="tablist">
            @foreach($locales as $i => $locale)
                <button type="button" class="{{ $i === 0 ? 'active' : '' }}" data-tab="{{ $locale }}" role="tab">{{ strtoupper($locale) }}</button>
            @endforeach
        </div>

        @foreach($locales as $locale)
            @php $tr = $post->translations->firstWhere('locale', $locale); @endphp
            <div class="tab-panel {{ $loop->first ? 'active' : '' }}" data-panel="{{ $locale }}">
                <div class="grid grid-2">
                    <div class="field">
                        <label>{{ __('ui.admin.title_label') }} ({{ $locale }})</label>
                        <input type="text" name="translations[{{ $locale }}][title]" value="{{ $tr?->title }}" required>
                    </div>
                    <div class="field">
                        <label>{{ __('ui.admin.slug') }} ({{ $locale }})</label>
                        <input type="text" name="translations[{{ $locale }}][slug]" value="{{ $tr?->slug }}" required>
                    </div>
                </div>
                <div class="field" style="margin-top:12px">
                    <label>{{ __('ui.admin.excerpt') }} ({{ $locale }})</label>
                    <textarea name="translations[{{ $locale }}][excerpt]" style="min-height:80px">{{ $tr?->excerpt }}</textarea>
                </div>
                <div class="field" style="margin-top:12px">
                    <label>{{ __('ui.admin.body') }} ({{ $locale }})</label>
                    <textarea name="translations[{{ $locale }}][body]" required>{{ $tr?->body }}</textarea>
                </div>
                <input type="hidden" name="translations[{{ $locale }}][locale]" value="{{ $locale }}">
            </div>
        @endforeach
    </div>

    @if($categories->isNotEmpty())
    <div class="card">
        <h3 style="margin:0 0 12px;font-size:15px">{{ __('ui.admin.categories') }}</h3>
        <div class="grid grid-2">
            @foreach($categories as $cat)
                @php $ct = $cat->translations->firstWhere('locale', 'fa') ?? $cat->translations->first(); @endphp
                <label style="display:flex;align-items:center;gap:8px;font-size:14px">
                    <input type="checkbox" name="categories[]" value="{{ $cat->id }}" @checked($post->categories->contains($cat->id))>
                    {{ $ct?->title ?? $cat->id }}
                </label>
            @endforeach
        </div>
    </div>
    @endif

    @if($tags->isNotEmpty())
    <div class="card">
        <h3 style="margin:0 0 12px;font-size:15px">{{ __('ui.admin.tags') }}</h3>
        <div class="grid grid-2">
            @foreach($tags as $tag)
                @php $tt = $tag->translations->firstWhere('locale', 'fa') ?? $tag->translations->first(); @endphp
                <label style="display:flex;align-items:center;gap:8px;font-size:14px">
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked($post->tags->contains($tag->id))>
                    {{ $tt?->title ?? $tag->id }}
                </label>
            @endforeach
        </div>
    </div>
    @endif

    <div style="display:flex;gap:10px;margin:20px 0">
        <button type="submit" class="btn primary">{{ __('ui.admin.save') }}</button>
        @if($post->exists)
            @php
                $previewLocale = $post->translations->first()?->locale ?? 'fa';
                $previewSlug = $post->translations->firstWhere('locale', $previewLocale)?->slug;
            @endphp
            @if($previewSlug)
                <a href="{{ URL::signedRoute('public.blog.preview', ['locale' => $previewLocale, 'slug' => $previewSlug], now()->addMinutes(15)) }}" target="_blank" rel="noopener" class="btn">{{ __('ui.admin.preview') }}</a>
            @endif
            @if($post->status->value !== 'published')
                <a href="{{ route('admin.cms.posts.index') }}" class="btn">{{ __('ui.admin.posts') }}</a>
            @endif
        @endif
    </div>
</form>

@if($post->exists && $post->revisions->isNotEmpty())
<div class="card">
    <h3 style="margin:0 0 12px;font-size:15px">{{ __('ui.admin.revisions') }}</h3>
    <table>
        <thead><tr><th>{{ __('ui.admin.locale') }}</th><th>{{ __('ui.admin.author') }}</th><th>{{ __('ui.admin.updated') }}</th></tr></thead>
        <tbody>
            @foreach($post->revisions->take(10) as $rev)
                <tr><td>{{ $rev->locale }}</td><td class="muted">{{ $rev->author?->name ?? '—' }}</td><td class="muted">{{ $rev->created_at?->format('Y-m-d H:i') }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<script>
document.querySelectorAll('.tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.querySelector('.tab-panel[data-panel="' + btn.dataset.tab + '"]').classList.add('active');
    });
});
</script>
@endsection
