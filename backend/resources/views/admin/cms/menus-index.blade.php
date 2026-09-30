@extends('admin.layout')
@section('content')
<div class="heading-row">
    <div>
        <h2>{{ __('ui.admin.menus') }}</h2>
        <p class="muted">{{ __('ui.admin.menus_help') }}</p>
    </div>
</div>

<div class="card">
    <h3>{{ __('ui.admin.new_menu') }}</h3>
    <form method="POST" action="{{ route('admin.cms.menus.store') }}">
        @csrf
        <div class="grid grid-2">
            <div class="field">
                <label>{{ __('ui.admin.slug') }}</label>
                <input type="text" name="slug" placeholder="main" required maxlength="60">
            </div>
            <div class="field">
                <label>{{ __('ui.admin.location') }}</label>
                <select name="location" required>
                    <option value="header">header</option>
                    <option value="footer">footer</option>
                    <option value="utility">utility</option>
                </select>
            </div>
        </div>
        <div class="grid grid-3">
            @foreach(['fa','ar','en'] as $locale)
                <div class="field" dir="{{ $locale === 'en' ? 'ltr' : 'rtl' }}">
                    <label>{{ __('ui.admin.title_label') }} ({{ strtoupper($locale) }})</label>
                    <input type="hidden" name="translations[{{ $locale }}][locale]" value="{{ $locale }}">
                    <input type="text" name="translations[{{ $locale }}][title]" required maxlength="120">
                </div>
            @endforeach
        </div>
        <button class="btn primary" type="submit">{{ __('ui.admin.save') }}</button>
    </form>
</div>

@foreach($menus as $menu)
    <div class="card">
        @php $fa = $menu->translations->firstWhere('locale', 'fa') ?? $menu->translations->first(); @endphp
        <div class="heading-row">
            <div>
                <h2>{{ $fa?->title ?? $menu->slug }}</h2>
                <span class="muted">{{ $menu->slug }} · {{ $menu->location }}</span>
            </div>
        </div>

        <details>
            <summary>{{ __('ui.admin.edit_menu') }}</summary>
            <form method="POST" action="{{ route('admin.cms.menus.update', $menu) }}" class="table-spaced">
                @csrf
                @method('PATCH')
                <div class="grid grid-2">
                    <div class="field">
                        <label>{{ __('ui.admin.slug') }}</label>
                        <input type="text" name="slug" value="{{ $menu->slug }}" required maxlength="60">
                    </div>
                    <div class="field">
                        <label>{{ __('ui.admin.location') }}</label>
                        <select name="location" required>
                            @foreach(['header','footer','utility'] as $location)
                                <option value="{{ $location }}" @selected($menu->location === $location)>{{ $location }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-3">
                    @foreach(['fa','ar','en'] as $locale)
                        @php $tr=$menu->translations->firstWhere('locale',$locale); @endphp
                        <div class="field" dir="{{ $locale === 'en' ? 'ltr' : 'rtl' }}">
                            <label>{{ __('ui.admin.title_label') }} ({{ strtoupper($locale) }})</label>
                            <input type="hidden" name="translations[{{ $locale }}][locale]" value="{{ $locale }}">
                            <input type="text" name="translations[{{ $locale }}][title]" value="{{ $tr?->title }}" required maxlength="120">
                        </div>
                    @endforeach
                </div>
                <button class="btn" type="submit">{{ __('ui.admin.save') }}</button>
            </form>
        </details>

        <div class="subsection">
            <h3>{{ __('ui.admin.add_item') }}</h3>
            <form method="POST" action="{{ route('admin.cms.menus.items.store', $menu) }}">
                @csrf
                <div class="grid grid-3">
                    @foreach(['fa','ar','en'] as $locale)
                        <div class="field" dir="{{ $locale === 'en' ? 'ltr' : 'rtl' }}">
                            <label>{{ __('ui.admin.label') }} ({{ strtoupper($locale) }})</label>
                            <input type="hidden" name="translations[{{ $locale }}][locale]" value="{{ $locale }}">
                            <input type="text" name="translations[{{ $locale }}][label]" required maxlength="120">
                        </div>
                    @endforeach
                </div>
                <div class="grid grid-2">
                    <div class="field">
                        <label>URL</label>
                        <input type="text" name="url" placeholder="/services/opg">
                        <small class="muted">{{ __('ui.admin.safe_url_help') }}</small>
                    </div>
                    <div class="field">
                        <label>{{ __('ui.admin.parent') }}</label>
                        <select name="parent_id">
                            <option value="">{{ __('ui.admin.none') }}</option>
                            @foreach($menu->items->sortBy('sort_order') as $parent)
                                @php $label=$parent->translations->firstWhere('locale','fa') ?? $parent->translations->first(); @endphp
                                <option value="{{ $parent->id }}">{{ $label?->label ?? '#'.$parent->id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>{{ __('ui.admin.target') }}</label>
                        <select name="target">
                            <option value="_self">{{ __('ui.admin.same_window') }}</option>
                            <option value="_blank">{{ __('ui.admin.new_window') }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>{{ __('ui.admin.order') }}</label>
                        <input type="number" name="sort_order" min="0" max="10000" value="0">
                    </div>
                </div>
                <input type="hidden" name="is_active" value="1">
                <button class="btn primary" type="submit">{{ __('ui.admin.add_item') }}</button>
            </form>
        </div>

        @if($menu->items->isNotEmpty())
            <div class="menu-admin-list">
                @foreach($menu->items->sortBy('sort_order') as $item)
                    @php $itemFa=$item->translations->firstWhere('locale','fa') ?? $item->translations->first(); @endphp
                    <details class="menu-admin-item">
                        <summary>
                            <strong>{{ $itemFa?->label ?? '#'.$item->id }}</strong>
                            <span class="muted">{{ $item->url ?? '—' }} · #{{ $item->sort_order }} · {{ $item->is_active ? __('ui.admin.active') : __('ui.admin.inactive') }}</span>
                        </summary>
                        <form method="POST" action="{{ route('admin.cms.menus.items.update', [$menu, $item]) }}">
                            @csrf
                            @method('PATCH')
                            <div class="grid grid-3">
                                @foreach(['fa','ar','en'] as $locale)
                                    @php $tr=$item->translations->firstWhere('locale',$locale); @endphp
                                    <div class="field" dir="{{ $locale === 'en' ? 'ltr' : 'rtl' }}">
                                        <label>{{ __('ui.admin.label') }} ({{ strtoupper($locale) }})</label>
                                        <input type="hidden" name="translations[{{ $locale }}][locale]" value="{{ $locale }}">
                                        <input type="text" name="translations[{{ $locale }}][label]" value="{{ $tr?->label }}" required maxlength="120">
                                    </div>
                                @endforeach
                            </div>
                            <div class="grid grid-2">
                                <div class="field">
                                    <label>URL</label>
                                    <input type="text" name="url" value="{{ $item->url }}">
                                </div>
                                <div class="field">
                                    <label>{{ __('ui.admin.parent') }}</label>
                                    <select name="parent_id">
                                        <option value="">{{ __('ui.admin.none') }}</option>
                                        @foreach($menu->items->where('id','!=',$item->id)->sortBy('sort_order') as $parent)
                                            @php $label=$parent->translations->firstWhere('locale','fa') ?? $parent->translations->first(); @endphp
                                            <option value="{{ $parent->id }}" @selected($item->parent_id === $parent->id)>{{ $label?->label ?? '#'.$parent->id }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="field">
                                    <label>{{ __('ui.admin.target') }}</label>
                                    <select name="target">
                                        <option value="_self" @selected($item->target === '_self')>{{ __('ui.admin.same_window') }}</option>
                                        <option value="_blank" @selected($item->target === '_blank')>{{ __('ui.admin.new_window') }}</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label>{{ __('ui.admin.order') }}</label>
                                    <input type="number" name="sort_order" min="0" max="10000" value="{{ $item->sort_order }}">
                                </div>
                                <div class="field">
                                    <label>{{ __('ui.admin.status') }}</label>
                                    <select name="is_active">
                                        <option value="1" @selected($item->is_active)>{{ __('ui.admin.active') }}</option>
                                        <option value="0" @selected(!$item->is_active)>{{ __('ui.admin.inactive') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row-actions">
                                <button class="btn" type="submit">{{ __('ui.admin.save') }}</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.cms.menus.items.destroy', [$menu, $item]) }}" class="form-spaced">
                            @csrf
                            @method('DELETE')
                            <button class="btn sm danger" type="submit" data-confirm="{{ __('ui.admin.delete_confirm') }}">{{ __('ui.admin.delete') }}</button>
                        </form>
                    </details>
                @endforeach
            </div>
        @else
            <div class="empty">{{ __('ui.admin.empty') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.cms.menus.destroy', $menu) }}" class="form-spaced">
            @csrf
            @method('DELETE')
            <button class="btn sm danger" type="submit" data-confirm="{{ __('ui.admin.delete_confirm') }}">{{ __('ui.admin.delete_menu') }}</button>
        </form>
    </div>
@endforeach

{{ $menus->links() }}
@endsection
