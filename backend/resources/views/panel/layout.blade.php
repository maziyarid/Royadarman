@php
    $locale = $locale ?? app()->getLocale();
    $rtl = in_array($locale, ['fa', 'ar'], true);
    $nav = $navActive ?? 'panel';
    $isDemo = !empty($isDemo);
    $canManageMarketing = !empty($canManageMarketing);
    $panelKey = $panelKey ?? 'patient';
    $home = $locale === 'fa' ? route('public.home.fa') : route('public.home', ['locale' => $locale]);
    $showSupport = in_array($panelKey, ['patient', 'coordinator', 'owner', 'tech_admin'], true);
    $showHome = in_array($panelKey, ['patient', 'coordinator', 'clinic_rep'], true);
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('panel.roles.'.$panelKey.'.title')) · Royadarman</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/workspace.css?v=20260917">
    <script src="/assets/workspace.js?v=20260917" defer></script>
    @stack('scripts')
</head>
<body class="workspace-body" data-dialog-ok="{{ __('panel.dialog.ok') }}" data-dialog-cancel="{{ __('panel.dialog.cancel') }}" data-dialog-url="{{ __('panel.dialog.url') }}">
<a class="skip-link" href="#main">{{ __('ui.skip') }}</a>
<div class="app-shell" data-workspace>
    <aside class="app-sidebar">
        <div class="app-brand">
            <img src="/assets/brand-mark.svg" width="38" height="38" alt="">
            <div>
                <strong>{{ __('panel.brand') }}</strong>
                <span class="app-role">{{ __('ui.dashboard.roles.'.$panelKey) }} · {{ __('panel.roles.'.$panelKey.'.title') }}@if($isDemo) · {{ __('panel.demo_label') }}@endif</span>
            </div>
        </div>
        <nav class="app-nav" aria-label="{{ __('panel.brand') }}">
            <a class="{{ $nav === 'panel' ? 'active' : '' }}" href="{{ route('panel', ['locale' => $locale]) }}">{{ __('panel.nav.overview') }}</a>
            <a class="{{ $nav === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard', ['locale' => $locale]) }}">{{ __('ui.dashboard.title') }}</a>
            @if($showSupport)
                <a class="{{ $nav === 'support' ? 'active' : '' }}" href="{{ route('panel.support.index', ['locale' => $locale]) }}">{{ __('panel.nav.support') }}</a>
            @endif
            @if($showHome)
                <a class="{{ $nav === 'home-service' ? 'active' : '' }}" href="{{ route('panel.home-service.index', ['locale' => $locale]) }}">{{ __('panel.nav.home_service') }}</a>
            @endif
            <a class="{{ $nav === 'profile' ? 'active' : '' }}" href="{{ route('panel.profile', ['locale' => $locale]) }}">{{ __('panel.nav.profile') }}</a>
            @if($panelKey === 'patient')
                <a class="{{ $nav === 'request' ? 'active' : '' }}" href="{{ route('patient.request.create', ['locale' => $locale]) }}">{{ __('request.title') }}</a>
            @endif
            @if($canManageMarketing)
                <a class="{{ $nav === 'cms' ? 'active' : '' }}" href="{{ route('admin.cms.posts.index') }}">{{ __('ui.admin.title') }}</a>
                <a class="{{ $nav === 'marketing' ? 'active' : '' }}" href="{{ route('marketing.index', ['locale' => $locale]) }}">{{ __('panel.marketing') }}</a>
                <a class="{{ $nav === 'network' ? 'active' : '' }}" href="{{ route('network.index', ['locale' => $locale]) }}">{{ __('network.title') }}</a>
            @endif
            @if(!$isDemo)
                <div class="nav-sep"></div>
                <a href="{{ $home }}">{{ __('panel.back_home') }}</a>
            @endif
        </nav>
        <div class="app-sidebar-foot">
            <button class="btn" type="button" data-logout data-locale="{{ $locale }}" data-home="{{ $home }}">{{ __('panel.logout') }}</button>
        </div>
    </aside>
    <div class="app-main">
        <header class="app-topbar">
            <button class="btn mobile-nav" type="button" data-mobile-nav aria-label="{{ __('site.menu') }}">☰</button>
            <h1>@yield('heading', __('panel.roles.'.$panelKey.'.title'))</h1>
            <div class="app-top-actions">@yield('actions')</div>
        </header>
        <main id="main" class="app-content">
            @if(session('status'))
                <div class="notice success" role="status">{{ session('status') }}</div>
            @endif
            @if($isDemo)
                <p class="notice"><strong>{{ __('panel.demo_label') }}</strong> · {{ __('panel.demo_notice') }}</p>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<div id="rd-dialog-root"></div>
</body>
</html>
