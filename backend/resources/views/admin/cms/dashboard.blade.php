@extends('admin.layout')

@section('content')
<div class="heading-row">
    <div>
        <h2>{{ __('ui.admin.overview') }}</h2>
        <p class="muted">{{ __('ui.admin.overview_help') }}</p>
    </div>
    <a href="{{ route('admin.cms.posts.create') }}" class="btn primary">+ {{ __('ui.admin.new_post') }}</a>
</div>

<div class="metric-grid">
    @foreach([
        ['posts', 'posts'],
        ['pages', 'pages'],
        ['services', 'services'],
        ['published', 'published_count'],
        ['drafts', 'drafts'],
        ['media', 'media'],
        ['pending_comments', 'pending_comments'],
        ['redirects', 'redirects'],
    ] as [$key, $label])
        <a class="metric-card" href="{{ match($key) {
            'media' => route('admin.cms.media.index'),
            'pending_comments' => route('admin.cms.comments.index', ['status'=>'pending']),
            'redirects' => route('admin.cms.redirects.index'),
            default => route('admin.cms.posts.index'),
        } }}">
            <span>{{ __('ui.admin.'.$label) }}</span>
            <strong>{{ number_format($counts[$key]) }}</strong>
        </a>
    @endforeach
</div>

<div class="card">
    <div class="heading-row">
        <h3>{{ __('ui.admin.recent_content') }}</h3>
        <a class="btn sm" href="{{ route('admin.cms.posts.index') }}">{{ __('ui.admin.posts') }}</a>
    </div>
    @if($recentPosts->isEmpty())
        <div class="empty">{{ __('ui.admin.empty') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('ui.admin.title_label') }}</th><th>{{ __('ui.admin.type') }}</th><th>{{ __('ui.admin.status') }}</th><th>{{ __('ui.admin.updated') }}</th><th>{{ __('ui.admin.actions') }}</th></tr></thead>
            <tbody>
            @foreach($recentPosts as $post)
                @php $tr=$post->translations->firstWhere('locale', app()->getLocale()) ?? $post->translations->first(); @endphp
                <tr>
                    <td><strong>{{ $tr?->title ?? '—' }}</strong></td>
                    <td>{{ __('ui.admin.post_types.'.$post->type->value) }}</td>
                    <td><span class="badge {{ $post->status->value }}">{{ __('ui.admin.post_statuses.'.$post->status->value) }}</span></td>
                    <td class="muted">{{ $post->updated_at?->format('Y-m-d H:i') }}</td>
                    <td><a class="btn sm" href="{{ route('admin.cms.posts.show', $post) }}">{{ __('ui.admin.edit') }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card">
    <h3>{{ __('ui.admin.management_shortcuts') }}</h3>
    <div class="quick-grid">
        <a class="quick-card" href="{{ route('admin.cms.media.index') }}"><strong>{{ __('ui.admin.media') }}</strong><span>{{ __('ui.admin.media_help') }}</span></a>
        <a class="quick-card" href="{{ route('admin.cms.menus.index') }}"><strong>{{ __('ui.admin.menus') }}</strong><span>{{ __('ui.admin.menus_help') }}</span></a>
        <a class="quick-card" href="{{ route('admin.cms.seo.index') }}"><strong>{{ __('ui.admin.seo') }}</strong><span>{{ __('ui.admin.seo_help') }}</span></a>
        <a class="quick-card" href="{{ route('admin.cms.redirects.index') }}"><strong>{{ __('ui.admin.redirects') }}</strong><span>{{ __('ui.admin.redirects_help') }}</span></a>
    </div>
</div>
@endsection
