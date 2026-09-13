@extends('admin.layout')
@section('content')
<h2 class="section-title">{{ __('ui.admin.seo') }}</h2>

<form class="filters" method="GET">
    <div class="field"><label>{{ __('ui.admin.entity_type') }}</label><input type="text" name="entity_type" value="{{ $filters['entity_type'] ?? '' }}" placeholder="App\Models\Cms\Post"></div>
    <button class="btn" type="submit">{{ __('ui.admin.search') }}</button>
</form>

<div class="card">
    @if($seo->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
    <table>
        <thead><tr><th scope="col">{{ __('ui.admin.locale') }}</th><th scope="col">{{ __('ui.admin.seo_title') }}</th><th scope="col">{{ __('ui.admin.focus_keyword') }}</th><th scope="col">{{ __('ui.admin.entity') }}</th><th scope="col">{{ __('ui.admin.actions') }}</th></tr></thead>
        <tbody>
        @foreach($seo as $s)
            <tr><td>{{ $s->locale }}</td><td>{{ $s->seo_title ?? '—' }}</td><td class="muted">{{ $s->focus_keyword ?? '—' }}</td><td><span class="muted">{{ Str::afterLast($s->entity_type, '\\') }}#{{ $s->entity_id }}</span></td>
            <td>@if($s->entity_type === 'App\\Models\\Cms\\Post')<a class="btn sm" href="{{ route('admin.cms.seo.edit', $s->entity_id) }}">{{ __('ui.admin.edit') }}</a>@endif</td></tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
{{ $seo->links() }}
@endsection
