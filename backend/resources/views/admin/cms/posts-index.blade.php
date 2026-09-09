@extends('admin.layout')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin:20px 0">
    <h2 style="margin:0;font-size:20px">{{ __('ui.admin.posts') }}</h2>
    <a href="{{ route('admin.cms.posts.create') }}" class="btn primary">+ {{ __('ui.admin.new_post') }}</a>
</div>

<form method="GET" class="filters">
    <div class="field">
        <label>{{ __('ui.admin.search') }}</label>
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.admin.search_placeholder') }}">
    </div>
    <div class="field">
        <label>{{ __('ui.admin.filter_type') }}</label>
        <select name="type">
            <option value="">{{ __('ui.admin.all') }}</option>
            @foreach($types as $t)<option value="{{ $t->value }}" @selected(($filters['type'] ?? '') === $t->value)>{{ $t->value }}</option>@endforeach
        </select>
    </div>
    <div class="field">
        <label>{{ __('ui.admin.filter_status') }}</label>
        <select name="status">
            <option value="">{{ __('ui.admin.all') }}</option>
            @foreach($statuses as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->value }}</option>@endforeach
        </select>
    </div>
    <button type="submit" class="btn primary">{{ __('ui.admin.search') }}</button>
</form>

<div class="card" style="padding:0;overflow:hidden">
    @if($posts->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
    <table>
        <thead><tr><th>{{ __('ui.admin.title_label') }}</th><th>{{ __('ui.admin.type') }}</th><th>{{ __('ui.admin.status') }}</th><th>{{ __('ui.admin.author') }}</th><th>{{ __('ui.admin.updated') }}</th><th>{{ __('ui.admin.actions') }}</th></tr></thead>
        <tbody>
        @foreach($posts as $post)
            @php $tr = $post->translations->firstWhere('locale', app()->getLocale()) ?? $post->translations->first(); @endphp
            <tr>
                <td><strong>{{ $tr?->title ?? '—' }}</strong><br><small class="muted">/{{ $tr?->locale }}/{{ $tr?->slug }}</small></td>
                <td>{{ $post->type->value }}</td>
                <td><span class="badge {{ $post->status->value }}">{{ $post->status->value }}</span></td>
                <td class="muted">{{ $post->author?->name ?? '—' }}</td>
                <td class="muted">{{ $post->updated_at?->format('Y-m-d') }}</td>
                <td class="row-actions">
                    <a href="{{ route('admin.cms.posts.show', $post) }}" class="btn sm">{{ __('ui.admin.edit') }}</a>
                    @if($post->status->value === 'published')
                        <form method="POST" action="{{ route('admin.cms.posts.unpublish', $post) }}">@csrf<button class="btn sm" type="submit">{{ __('ui.admin.unpublish') }}</button></form>
                    @else
                        <form method="POST" action="{{ route('admin.cms.posts.publish', $post) }}">@csrf<button class="btn sm primary" type="submit">{{ __('ui.admin.publish') }}</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.cms.posts.destroy', $post) }}" onsubmit="return confirm('{{ __('ui.admin.delete_confirm') }}')">@csrf@method('DELETE')<button class="btn sm danger" type="submit">{{ __('ui.admin.delete') }}</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@if($posts->hasPages())
<nav class="pagination">
    @foreach(range(1, $posts->lastPage()) as $p)
        <a href="?{{ http_build_query(array_merge($filters, ['page' => $p])) }}" class="{{ $p === $posts->currentPage() ? 'active' : '' }}">{{ $p }}</a>
    @endforeach
</nav>
@endif
@endsection
