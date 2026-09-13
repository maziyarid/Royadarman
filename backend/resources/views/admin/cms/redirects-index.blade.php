@extends('admin.layout')
@section('content')
<h2 style="margin:8px 0">{{ __('ui.admin.redirects') }}</h2>

<div class="card">
    <form method="POST" action="{{ route('admin.cms.redirects.store') }}" class="filters">@csrf
        <div class="field"><label>{{ __('ui.admin.source_path') }}</label><input type="text" name="source_path" placeholder="/old-page" required></div>
        <div class="field"><label>{{ __('ui.admin.destination_url') }}</label><input type="text" name="destination_url" placeholder="/fa/new-page" required></div>
        <div class="field"><label>{{ __('ui.admin.status_code') }}</label><select name="status_code"><option value="301">301</option><option value="302">302</option></select></div>
        <button class="btn primary" type="submit">{{ __('ui.admin.save') }}</button>
    </form>
</div>

<div class="card">
    @if($redirects->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
    <table>
        <thead><tr><th scope="col">{{ __('ui.admin.source_path') }}</th><th scope="col">{{ __('ui.admin.destination_url') }}</th><th scope="col">{{ __('ui.admin.status_code') }}</th><th scope="col">{{ __('ui.admin.hits') }}</th><th scope="col">{{ __('ui.admin.actions') }}</th></tr></thead>
        <tbody>
        @foreach($redirects as $r)
            <tr><td>{{ $r->source_path }}</td><td>{{ $r->destination_url }}</td><td><span class="badge {{ $r->status_code === 301 ? 'published' : 'in_review' }}">{{ $r->status_code }}</span></td><td class="muted">{{ $r->hit_count }}</td>
            <td class="row-actions"><form method="POST" action="{{ route('admin.cms.redirects.destroy', $r) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit" onclick="return confirm('{{ __('ui.admin.delete_confirm') }}')">{{ __('ui.admin.delete') }}</button></form></td></tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
{{ $redirects->links() }}
@endsection
