@php
    $routeName = match($pageKey) {
        'home' => 'public.home',
        'opg' => 'public.opg',
        'home_dentistry' => 'public.home-dentistry',
        'referrals' => 'public.referrals',
        'contact' => 'public.contact',
    };
    $rtl = in_array(app()->getLocale(), ['fa', 'ar'], true);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ route($routeName, ['locale' => app()->getLocale()]) }}">
    @foreach(['fa','ar','en'] as $alternateLocale)
        <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ route($routeName, ['locale' => $alternateLocale]) }}">
    @endforeach
    <style>
        :root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#14221d;background:#f7f9f8}*{box-sizing:border-box}body{margin:0}.shell{max-width:1080px;margin-inline:auto;padding:24px}.nav{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-block-end:42px}.brand{font-weight:900;color:#134437;text-decoration:none;font-size:1.1rem}.links{display:flex;gap:10px;flex-wrap:wrap}.links a{color:#36544a;text-decoration:none;padding:8px 10px;border-radius:9px}.hero{padding:clamp(28px,6vw,72px);background:#fff;border:1px solid #e0e8e4;border-radius:24px}.hero h1{font-size:clamp(2rem,5vw,3.8rem);line-height:1.12;margin:0 0 18px;max-width:900px}.lead{font-size:1.12rem;line-height:1.8;color:#536760;max-width:800px}.body{margin-block-start:22px;background:#fff;border:1px solid #e0e8e4;border-radius:18px;padding:26px;white-space:pre-line;line-height:1.95;color:#263b34}.boundary{margin-block-start:22px;padding:16px 18px;border-inline-start:4px solid #134437;background:#eef4f1;border-radius:10px;line-height:1.7}.footer{padding-block:36px;color:#65756f;font-size:.9rem}@media(max-width:700px){.nav{align-items:flex-start;flex-direction:column}.shell{padding:16px}.hero{padding:26px 20px}}
    </style>
</head>
<body>
<main class="shell">
    <nav class="nav">
        <a class="brand" href="{{ route('public.home', ['locale' => app()->getLocale()]) }}">Royadarman</a>
        <div class="links">
            <a href="{{ route('public.opg', ['locale' => app()->getLocale()]) }}">OPG</a>
            <a href="{{ route('public.home-dentistry', ['locale' => app()->getLocale()]) }}">{{ app()->getLocale() === 'en' ? 'Home dentistry' : (app()->getLocale() === 'ar' ? 'طب الأسنان المنزلي' : 'دندانپزشکی در منزل') }}</a>
            <a href="{{ route('public.referrals', ['locale' => app()->getLocale()]) }}">{{ app()->getLocale() === 'en' ? 'Referrals' : (app()->getLocale() === 'ar' ? 'الإحالات' : 'معرفی مراکز') }}</a>
            <a href="{{ route('public.contact', ['locale' => app()->getLocale()]) }}">{{ app()->getLocale() === 'en' ? 'Contact' : (app()->getLocale() === 'ar' ? 'تواصل' : 'تماس') }}</a>
        </div>
    </nav>

    <section class="hero">
        <h1>{{ $title }}</h1>
        @if($excerpt)<p class="lead">{{ $excerpt }}</p>@endif
    </section>
    <section class="body">{{ $body }}</section>
    <aside class="boundary">{{ __('ui.boundary') }}</aside>
    <footer class="footer">{{ __('ui.footer') }}</footer>
</main>
</body>
</html>
