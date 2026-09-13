@extends('admin.layout')
@section('content')
@php $tr = $post->translations->firstWhere('locale', 'fa') @endphp
<h2 class="section-title">{{ __('ui.admin.seo') }} — {{ $tr?->title ?? '#'.$post->id }}</h2>
<form method="POST" action="{{ route('admin.cms.seo.update', $post) }}">@csrf @method('PATCH')
    <div class="tabs">@foreach($locales as $i => $loc)<button type="button" class="@if($i===0) active @endif" data-tab="{{ $loc }}">{{ strtoupper($loc) }}</button>@endforeach</div>
    @foreach($locales as $loc)
        @php $s = $seo[$loc] ?? null @endphp
        <div class="tab-panel @if($loc === $locales[0]) active @endif" data-panel="{{ $loc }}">
            <input type="hidden" name="seo[{{ $loc }}][locale]" value="{{ $loc }}">
            <div class="field"><label>{{ __('ui.admin.seo_title') }}</label><input type="text" name="seo[{{ $loc }}][seo_title]" value="{{ old("seo.$loc.seo_title", $s?->seo_title ?? '') }}"></div>
            <div class="field"><label>{{ __('ui.admin.meta_description') }}</label><input type="text" name="seo[{{ $loc }}][meta_description]" value="{{ old("seo.$loc.meta_description", $s?->meta_description ?? '') }}"></div>
            <div class="grid grid-2">
                <div class="field"><label>{{ __('ui.admin.canonical_url') }}</label><input type="text" name="seo[{{ $loc }}][canonical_url]" value="{{ old("seo.$loc.canonical_url", $s?->canonical_url ?? '') }}"></div>
                <div class="field"><label>{{ __('ui.admin.robots_directive') }}</label><input type="text" name="seo[{{ $loc }}][robots_directive]" value="{{ old("seo.$loc.robots_directive", $s?->robots_directive ?? 'index, follow') }}"></div>
            </div>
            <div class="grid grid-2">
                <div class="field"><label>{{ __('ui.admin.og_title') }}</label><input type="text" name="seo[{{ $loc }}][og_title]" value="{{ old("seo.$loc.og_title", $s?->og_title ?? '') }}"></div>
                <div class="field"><label>{{ __('ui.admin.focus_keyword') }}</label><input type="text" name="seo[{{ $loc }}][focus_keyword]" value="{{ old("seo.$loc.focus_keyword", $s?->focus_keyword ?? '') }}"></div>
            </div>
            <div class="field"><label>{{ __('ui.admin.og_description') }}</label><input type="text" name="seo[{{ $loc }}][og_description]" value="{{ old("seo.$loc.og_description", $s?->og_description ?? '') }}"></div>
        </div>
    @endforeach
    <button class="btn primary" type="submit">{{ __('ui.admin.save') }}</button>
</form>
@endsection
