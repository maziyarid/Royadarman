@extends('admin.layout')
@section('content')
<h2 class="section-title">{{ __('ui.admin.categories') }}</h2>
<a class="btn primary" href="{{ route('admin.cms.categories.create') }}">{{ __('ui.admin.new_category') }}</a>

<form class="filters" method="GET">
    <div class="field"><label>{{ __('ui.admin.search') }}</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.admin.search_placeholder') }}"></div>
    <button class="btn" type="submit">{{ __('ui.admin.search') }}</button>
</form>

<div class="card">
    @if($categories->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
    <table>
        <thead><tr><th scope="col">{{ __('ui.admin.name') }}</th><th scope="col">{{ __('ui.admin.slug') }}</th><th scope="col">{{ __('ui.admin.parent') }}</th><th scope="col">{{ __('ui.admin.actions') }}</th></tr></thead>
        <tbody>
        @foreach($categories as $cat)
            @php $fa = $cat->translations->firstWhere('locale', 'fa') ?? $cat->translations->first()
            @endphp
            <tr>
                <td>{{ $fa?->name ?? '#' . $cat->id }}</td>
                <td>{{ $fa?->slug ?? '' }}</td>
                <td>@if($cat->parent && ($p = $cat->parent->translations->firstWhere('locale', 'fa'))) {{ $p->name }} @else <span class="muted">{{ __('ui.admin.none') }}</span> @endif</td>
                <td class="row-actions">
                    <a class="btn sm" href="{{ route('admin.cms.categories.edit', $cat) }}">{{ __('ui.admin.edit') }}</a>
                    <form method="POST" action="{{ route('admin.cms.categories.destroy', $cat) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit" data-confirm="{{ __('ui.admin.delete_confirm') }}">{{ __('ui.admin.delete') }}</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
{{ $categories->links() }}
@endsection
