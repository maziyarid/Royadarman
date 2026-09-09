@extends('admin.layout')
@section('content')
<h2 style="margin:8px 0">{{ __('ui.admin.menus') }}</h2>

<div class="card">
    <form method="POST" action="{{ route('admin.cms.menus.store') }}" class="filters">@csrf
        <div class="field"><label>{{ __('ui.admin.slug') }}</label><input type="text" name="slug" placeholder="main" required></div>
        <div class="field"><label>{{ __('ui.admin.location') }}</label><input type="text" name="location" placeholder="header" required></div>
        <div class="field"><label>{{ __('ui.admin.title') }} (fa)</label><input type="text" name="title_fa" required></div>
        <button class="btn primary" type="submit">{{ __('ui.admin.save') }}</button>
    </form>
</div>

@foreach($menus as $menu)
    @php $fa = $menu->translations->firstWhere('locale', 'fa') @endphp
    <div class="card">
        <h2>{{ $fa?->title ?? $menu->slug }} <span class="muted">({{ $menu->location }})</span></h2>
        <form method="POST" action="{{ route('admin.cms.menus.items.store', $menu) }}" class="filters">@csrf
            <div class="field"><label>{{ __('ui.admin.label') }} (fa)</label><input type="text" name="label_fa" required></div>
            <div class="field"><label>URL</label><input type="text" name="url" placeholder="/fa/page"></div>
            <div class="field"><label>{{ __('ui.admin.parent') }}</label>
                <select name="parent_id"><option value="">{{ __('ui.admin.none') }}</option>
                @foreach($menu->items as $i)@php $l = $i->translations->firstWhere('locale','fa') @endphp<option value="{{ $i->id }}">{{ $l?->label ?? '#'.$i->id }}</option>@endforeach
                </select>
            </div>
            <button class="btn primary" type="submit">{{ __('ui.admin.add_item') }}</button>
        </form>
        @if($menu->items->isNotEmpty())
        <table style="margin-top:12px">
            <thead><tr><th>{{ __('ui.admin.label') }}</th><th>URL</th><th>{{ __('ui.admin.actions') }}</th></tr></thead>
            <tbody>@foreach($menu->items as $i)@php $l = $i->translations->firstWhere('locale','fa') @endphp
                <tr><td>{{ $l?->label ?? '#'.$i->id }}</td><td class="muted">{{ $i->url ?? '—' }}</td>
                <td class="row-actions"><form method="POST" action="{{ route('admin.cms.menus.items.destroy', [$menu, $i]) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit" onclick="return confirm('{{ __('ui.admin.delete_confirm') }}')">{{ __('ui.admin.delete') }}</button></form></td></tr>
            @endforeach</tbody>
        </table>
        @else
        <div class="empty">{{ __('ui.admin.empty') }}</div>
        @endif
        <form method="POST" action="{{ route('admin.cms.menus.destroy', $menu) }}" style="margin-top:10px">@csrf @method('DELETE')<button class="btn sm danger" type="submit" onclick="return confirm('{{ __('ui.admin.delete_confirm') }}')">{{ __('ui.admin.delete_menu') }}</button></form>
    </div>
@endforeach
{{ $menus->links() }}
@endsection
