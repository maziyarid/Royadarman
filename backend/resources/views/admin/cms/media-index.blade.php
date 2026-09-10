@extends('admin.layout')
@section('content')
<h2 style="margin:8px 0">{{ __('ui.admin.media') }}</h2>

<div class="card">
    <form method="POST" action="{{ route('admin.cms.media.store') }}" enctype="multipart/form-data" class="filters">@csrf
        <div class="field"><label>{{ __('ui.admin.filename') }}</label><input type="file" name="file" accept="image/*" required></div>
        <button class="btn primary" type="submit">{{ __('ui.admin.upload') }}</button>
    </form>
</div>

<form class="filters" method="GET">
    <div class="field"><label>{{ __('ui.admin.search') }}</label><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('ui.admin.alt') }}…"></div>
    <button class="btn" type="submit">{{ __('ui.admin.search') }}</button>
</form>

<div class="card">
    @if($media->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
    <table>
        <thead><tr><th scope="col">{{ __('ui.admin.filename') }}</th><th scope="col">{{ __('ui.admin.dimensions') }}</th><th scope="col">{{ __('ui.admin.filesize') }}</th><th scope="col">{{ __('ui.admin.actions') }}</th></tr></thead>
        <tbody>
        @foreach($media as $m)
            <tr>
                <td>{{ $m->original_filename }}</td>
                <td class="muted">@if($m->width && $m->height){{ $m->width }}×{{ $m->height }}@else—@endif</td>
                <td>{{ number_format($m->byte_size / 1024, 1) }} KB</td>
                <td class="row-actions">
                    <form method="POST" action="{{ route('admin.cms.media.destroy', $m) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit" onclick="return confirm('{{ __('ui.admin.delete_confirm') }}')">{{ __('ui.admin.delete') }}</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
{{ $media->links() }}
@endsection
