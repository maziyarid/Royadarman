@extends('admin.layout')
@section('content')
<h2 class="section-title">{{ __('ui.admin.tags') }}</h2>

<form class="filters" method="GET">
    <div class="field"><label>{{ __('ui.admin.search') }}</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.admin.search_placeholder') }}"></div>
    <button class="btn" type="submit">{{ __('ui.admin.search') }}</button>
</form>

<div class="card">
    <form method="POST" action="{{ route('admin.cms.tags.store') }}" class="filters">@csrf
        <div class="field"><label>{{ __('ui.admin.new_tag') }} (fa)</label><input type="text" name="translations[fa][name]" placeholder="{{ __('ui.admin.name') }}" required></div>
        <div class="field"><label>slug (fa)</label><input type="text" name="translations[fa][slug]" placeholder="{{ __('ui.admin.slug') }}" required></div>
        <input type="hidden" name="translations[fa][locale]" value="fa">
        <button class="btn primary" type="submit">{{ __('ui.admin.save') }}</button>
    </form>
</div>

<div class="card">
    @if($tags->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
    <table>
        <thead><tr><th scope="col">{{ __('ui.admin.name') }}</th><th scope="col">{{ __('ui.admin.slug') }}</th><th scope="col">{{ __('ui.admin.actions') }}</th></tr></thead>
        <tbody>
        @foreach($tags as $tag)
            @php $fa = $tag->translations->firstWhere('locale', 'fa') ?? $tag->translations->first() @endphp
            <tr>
                <td>{{ $fa?->name ?? '#'.$tag->id }}</td>
                <td>{{ $fa?->slug ?? '' }}</td>
                <td class="row-actions">
                    <form method="POST" action="{{ route('admin.cms.tags.destroy', $tag) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit" data-confirm="{{ __('ui.admin.delete_confirm') }}">{{ __('ui.admin.delete') }}</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
{{ $tags->links() }}
@endsection
