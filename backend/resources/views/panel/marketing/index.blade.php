@extends('panel.layout')
@section('title', __('panel.cms.title'))
@section('heading', __('panel.cms.title'))
@section('actions')
    <a class="btn primary" href="{{ route('marketing.create', ['locale' => $locale]) }}">{{ __('panel.cms.new') }}</a>
@endsection
@section('content')
<p class="hint">{{ __('panel.cms.intro') }}</p>
<section class="card">
    @if($pages->isEmpty())
        <div class="empty">{{ __('panel.cms.empty') }}</div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">{{ __('panel.cms.slug') }}</th>
                        <th scope="col">{{ __('panel.cms.locale') }}</th>
                        <th scope="col">{{ __('panel.cms.page_title') }}</th>
                        <th scope="col">{{ __('panel.table.status') }}</th>
                        <th scope="col">{{ __('panel.cms.version') }}</th>
                        <th scope="col">{{ __('ui.admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pages as $page)
                        <tr>
                            <td><bdi>{{ $page->slug }}</bdi></td>
                            <td>{{ strtoupper($page->locale) }}</td>
                            <td>{{ $page->title }}</td>
                            <td><span class="badge {{ $page->status }}">{{ __('panel.cms.status_'.$page->status) }}</span></td>
                            <td>{{ $page->version }}</td>
                            <td><a class="btn sm" href="{{ route('marketing.edit', ['locale' => $locale, 'page' => $page->id]) }}">{{ __('panel.cms.edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
