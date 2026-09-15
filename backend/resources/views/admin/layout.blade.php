@php
    $locale = in_array(auth()->user()?->locale, ['fa', 'ar', 'en'], true) ? auth()->user()->locale : (in_array(app()->getLocale(), ['fa', 'ar', 'en'], true) ? app()->getLocale() : 'fa');
    $rtl = in_array($locale, ['fa', 'ar'], true);
    $home = $locale === 'fa' ? url('/') : url('/'.$locale);
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.admin.title') }} · Royadarman</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/workspace.css?v=20260915">
    <script src="/assets/workspace.js?v=20260915" defer></script>
</head>
<body class="workspace-body" data-dialog-ok="{{ __('panel.dialog.ok') }}" data-dialog-cancel="{{ __('panel.dialog.cancel') }}" data-dialog-url="{{ __('panel.dialog.url') }}">
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<div class="app-shell" data-workspace>
    <aside class="app-sidebar">
        <div class="app-brand">
            <img src="/assets/brand-mark.svg" width="38" height="38" alt="">
            <div>
                <strong>{{ __('panel.brand') }}</strong>
                <span class="app-role">{{ __('ui.admin.title') }}</span>
            </div>
        </div>
        <nav class="app-nav" aria-label="{{ __('ui.admin.title') }}">
            <a href="{{ route('dashboard', ['locale' => $locale]) }}">{{ __('ui.dashboard.title') }}</a>
            <a href="{{ route('admin.cms.posts.index') }}">{{ __('ui.admin.posts') }}</a>
            <a href="{{ route('admin.cms.categories.index') }}">{{ __('ui.admin.categories') }}</a>
            <a href="{{ route('admin.cms.tags.index') }}">{{ __('ui.admin.tags') }}</a>
            <a href="{{ route('admin.cms.media.index') }}">{{ __('ui.admin.media') }}</a>
            <a href="{{ route('admin.cms.menus.index') }}">{{ __('ui.admin.menus') }}</a>
            <a href="{{ route('admin.cms.redirects.index') }}">{{ __('ui.admin.redirects') }}</a>
            <a href="{{ route('admin.cms.comments.index') }}">{{ __('ui.admin.comments') }}</a>
            <a href="{{ route('admin.cms.seo.index') }}">{{ __('ui.admin.seo') }}</a>
            <div class="nav-sep"></div>
            <a href="{{ route('marketing.index', ['locale' => $locale]) }}">{{ __('panel.marketing') }}</a>
            <a href="{{ route('network.index', ['locale' => $locale]) }}">{{ __('network.title') }}</a>
            <a href="{{ $home }}" target="_blank" rel="noopener">{{ __('ui.admin.view_site') }}</a>
        </nav>
        <div class="app-sidebar-foot">
            <button class="btn" type="button" data-logout data-locale="{{ $locale }}" data-home="{{ $home }}">{{ __('ui.admin.logout') }}</button>
        </div>
    </aside>
    <div class="app-main">
        <header class="app-topbar">
            <button class="btn mobile-nav" type="button" data-mobile-nav aria-label="{{ __('site.menu') }}">☰</button>
            <h1>{{ __('ui.admin.title') }}</h1>
            <div class="app-top-actions"><a class="btn" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('panel.brand') }}</a></div>
        </header>
        <main id="main" class="app-content">
            @if(session('status'))
                <div class="notice success" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<div id="rd-dialog-root"></div>
</body>
</html>
