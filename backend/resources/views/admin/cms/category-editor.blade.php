@extends('admin.layout')
@section('content')
<h2 class="section-title">{{ isset($category) && $category->exists ? __('ui.admin.edit') : __('ui.admin.new_category') }}</h2>
<form method="POST" action="{{ isset($category) && $category->exists ? route('admin.cms.categories.update', $category) : route('admin.cms.categories.store') }}">
    @csrf
    @if(isset($category) && $category->exists) @method('PATCH') @endif
    <div class="field compact-copy">
        <label>{{ __('ui.admin.parent') }}</label>
        <select name="parent_id">
            <option value="">{{ __('ui.admin.none') }}</option>
            @foreach($parents as $p)
                @php $pn = $p->translations->firstWhere('locale', 'fa') ?? $p->translations->first() @endphp
                <option value="{{ $p->id }}" @if(($category->parent_id ?? null) === $p->id) selected @endif>{{ $pn?->name ?? '#'.$p->id }}</option>
            @endforeach
        </select>
    </div>
    <div class="tabs" id="catTabs">
        @foreach($locales as $i => $loc)<button type="button" class="@if($i===0) active @endif" data-tab="{{ $loc }}">{{ strtoupper($loc) }}</button>@endforeach
    </div>
    @foreach($locales as $loc)
        @php $tr = $category->translations->firstWhere('locale', $loc) ?? null @endphp
        <div class="tab-panel @if($loc === $locales[0]) active @endif" data-panel="{{ $loc }}">
            <div class="grid grid-2">
                <div class="field"><label>{{ __('ui.admin.name') }} ({{ $loc }})</label><input type="text" name="translations[{{ $loc }}][name]" value="{{ old("translations.$loc.name", $tr?->name ?? '') }}"></div>
                <div class="field"><label>{{ __('ui.admin.slug') }} ({{ $loc }})</label><input type="text" name="translations[{{ $loc }}][slug]" value="{{ old("translations.$loc.slug", $tr?->slug ?? '') }}"></div>
            </div>
            <div class="field"><label>{{ __('ui.admin.excerpt') }} ({{ $loc }})</label><textarea name="translations[{{ $loc }}][description]" class="textarea-sm">{{ old("translations.$loc.description", $tr?->description ?? '') }}</textarea></div>
            <input type="hidden" name="translations[{{ $loc }}][locale]" value="{{ $loc }}">
        </div>
    @endforeach
    <button class="btn primary" type="submit">{{ __('ui.admin.save') }}</button>
</form>
@endsection
