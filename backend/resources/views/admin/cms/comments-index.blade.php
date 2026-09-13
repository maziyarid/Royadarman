@extends('admin.layout')
@section('content')
<h2 style="margin:8px 0">{{ __('ui.admin.comments') }}</h2>

<form class="filters" method="GET">
    <div class="field"><label>{{ __('ui.admin.status') }}</label><select name="status"><option value="">{{ __('ui.admin.all') }}</option>@foreach($statuses as $s)<option value="{{ $s }}" @if(($filters['status'] ?? '') === $s) selected @endif>{{ __('ui.admin.comment_status.'.$s) }}</option>@endforeach</select></div>
    <button class="btn" type="submit">{{ __('ui.admin.search') }}</button>
</form>

<div class="card">
    @if($comments->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
    <table>
        <thead><tr><th scope="col">{{ __('ui.admin.author') }}</th><th scope="col">{{ __('ui.admin.body') }}</th><th scope="col">{{ __('ui.admin.status') }}</th><th scope="col">{{ __('ui.admin.col_updated') }}</th><th scope="col">{{ __('ui.admin.actions') }}</th></tr></thead>
        <tbody>
        @foreach($comments as $c)
            @php $st = $c->status->value @endphp
            <tr><td>{{ $c->author_name ?? ($c->author?->name ?? '—') }}</td><td style="max-width:400px">{{ Str::limit($c->body, 100) }}</td>
            <td><span class="badge {{ $st === 'approved' ? 'published' : ($st === 'spam' ? 'archived' : 'in_review') }}">{{ __('ui.admin.comment_status.'.$st) }}</span></td>
            <td class="muted">{{ $c->created_at }}</td>
            <td class="row-actions">
                @if($st !== 'approved')<form method="POST" action="{{ route('admin.cms.comments.approve', $c) }}">@csrf<button class="btn sm" type="submit">{{ __('ui.admin.approve') }}</button></form>@endif
                @if($st !== 'spam')<form method="POST" action="{{ route('admin.cms.comments.spam', $c) }}">@csrf<button class="btn sm" type="submit">{{ __('ui.admin.mark_spam') }}</button></form>@endif
                <form method="POST" action="{{ route('admin.cms.comments.destroy', $c) }}">@csrf @method('DELETE')<button class="btn sm danger" type="submit" onclick="return confirm('{{ __('ui.admin.delete_confirm') }}')">{{ __('ui.admin.delete') }}</button></form>
            </td></tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
{{ $comments->links() }}
@endsection
