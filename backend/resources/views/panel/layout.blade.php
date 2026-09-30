@php
    $locale = $locale ?? app()->getLocale();
    $rtl = in_array($locale, ['fa', 'ar'], true);
    $nav = $navActive ?? 'panel';
    $isDemo = !empty($isDemo);
    $canManageMarketing = !empty($canManageMarketing);
    $canManageCms = !empty($canManageCms);
    $canManageIntegrations = !empty($canManageIntegrations);
    $canManageAdministrators = !empty($canManageAdministrators);
    $panelKey = $panelKey ?? 'patient';
    $home = $locale === 'fa' ? route('public.home.fa') : route('public.home', ['locale' => $locale]);
    $showSupport = in_array($panelKey, ['patient', 'coordinator', 'owner', 'tech_admin'], true);
    $showHome = in_array($panelKey, ['patient', 'coordinator', 'clinic_rep'], true);
    $showTasks = $panelKey === 'coordinator' && !$isDemo;
    $showAnalytics = $panelKey === 'owner' && !$isDemo;
    $showLaunchReadiness = in_array($panelKey, ['owner', 'tech_admin'], true) && !$isDemo;
    $showDeliveries = in_array($panelKey, ['owner', 'tech_admin'], true) && !$isDemo;
    $showPolicies = in_array($panelKey, ['owner', 'tech_admin'], true) && !$isDemo;
    $showCases = in_array($panelKey, ['coordinator', 'clinician'], true);
    $showCalendar = in_array($panelKey, ['coordinator', 'clinic_rep'], true) && !$isDemo;
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
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/assets/pwa/icon-192.png">
    <meta name="theme-color" content="#2947A3">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="stylesheet" href="/assets/workspace.css?v=20260929-deliveries">
    <script src="/assets/workspace.js?v=20260921" defer></script>
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
            @if($showCases)
                <a class="{{ $nav === 'cases' ? 'active' : '' }}" href="{{ route('panel.cases.index', ['locale' => $locale]) }}">{{ __('panel.nav.cases') }}</a>
            @endif
            @if($panelKey === 'coordinator' && !$isDemo)
                <a class="{{ $nav === 'tasks' ? 'active' : '' }}" href="{{ route('panel.tasks.index', ['locale' => $locale]) }}">{{ __('panel.nav.tasks') }}</a>
            @endif
            @if($showHome)
                <a class="{{ $nav === 'home-service' ? 'active' : '' }}" href="{{ route('panel.home-service.index', ['locale' => $locale]) }}">{{ __('panel.nav.home_service') }}</a>
            @endif
            <a class="{{ $nav === 'profile' ? 'active' : '' }}" href="{{ route('panel.profile', ['locale' => $locale]) }}">{{ __('panel.nav.profile') }}</a>
            @if($panelKey === 'patient')
                <a class="{{ $nav === 'request' ? 'active' : '' }}" href="{{ route('patient.request.create', ['locale' => $locale]) }}">{{ __('request.title') }}</a>
            @endif
            @if($canManageCms)
                <a class="{{ $nav === 'cms' ? 'active' : '' }}" href="{{ route('admin.cms.posts.index') }}">{{ __('ui.admin.title') }}</a>
            @endif
            @if($canManageMarketing)
                <a class="{{ $nav === 'marketing' ? 'active' : '' }}" href="{{ route('marketing.index', ['locale' => $locale]) }}">{{ __('panel.marketing') }}</a>
                <a class="{{ $nav === 'network' ? 'active' : '' }}" href="{{ route('network.index', ['locale' => $locale]) }}">{{ __('network.title') }}</a>
            @endif
            @if($canManageIntegrations)
                <a class="{{ $nav === 'integrations' ? 'active' : '' }}" href="{{ route('integrations.index', ['locale' => $locale]) }}">{{ __('panel.nav.integrations') }}</a>
            @endif
            @if($canManageAdministrators)
                <a class="{{ $nav === 'administrators' ? 'active' : '' }}" href="{{ route('administrators.index', ['locale' => $locale]) }}">{{ __('panel.nav.administrators') }}</a>
            @endif
            @if($showPolicies)
                <a class="{{ $nav === 'policies' ? 'active' : '' }}" href="{{ route('panel.policies.index', ['locale' => $locale]) }}">{{ __('panel.nav.policies') }}</a>
            @endif
            @if($showLaunchReadiness)
                <a class="{{ $nav === 'launch_readiness' ? 'active' : '' }}" href="{{ route('panel.launch-readiness.index', ['locale' => $locale]) }}">{{ __('panel.nav.launch_readiness') }}</a>
            @endif
            @if($showDeliveries)
                <a class="{{ $nav === 'deliveries' ? 'active' : '' }}" href="{{ route('panel.deliveries.index', ['locale' => $locale]) }}">{{ __('panel.nav.deliveries') }}</a>
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
            @if(!$isDemo)
                <form class="topbar-search" method="get" action="{{ route('panel.search.index', ['locale' => $locale]) }}" role="search">
                    <label class="sr-only" for="topbar-search">{{ __('panel.search.label') }}</label>
                    <input id="topbar-search" type="search" name="q" value="{{ $nav === 'search' ? request('q') : '' }}" placeholder="{{ __('panel.search.short_placeholder') }}" autocomplete="off">
                    <button type="submit" aria-label="{{ __('panel.search.submit') }}">{{ __('panel.search.submit') }}</button>
                </form>
            @endif
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
