@php $locale=app()->getLocale(); $pub=fn(string $name):string=>\App\Support\PublicUrl::to($name, $locale); @endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ in_array($locale,['fa','ar'],true)?'rtl':'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#2947a3">
<meta name="robots" content="noindex,nofollow">
<title>{{ __('ui.pres.title') }}</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/font.css?v=20260916">
<link rel="stylesheet" href="/assets/site.css?v=20260916">
<script src="/assets/site.js?v=20260916" defer></script>
</head>
<body>
@include('public.partials.header')
<main id="main">
<section class="page-hero">
    <div class="shell narrow">
        <p class="hero-kicker">{{ __('ui.staging_banner') }}</p>
        <h1>{{ __('ui.pres.title') }}</h1>
        <p>{{ __('ui.pres.lead') }}</p>
        @if(session('status'))
            <p class="boundary">{{ session('status') }}</p>
        @endif
        @if($handoffs === [])
            <p class="boundary">{{ __('ui.pres.handoff_off') }}</p>
        @endif
        <div class="pres-grid">
            @foreach(['client','coordinator','clinician','clinic','admin','tech'] as $alias)
                @if(isset($handoffs[$alias]))
                    <a class="pres-card" href="{{ $handoffs[$alias] }}">
                        <strong>{{ __('ui.pres.roles.'.$alias) }}</strong>
                        <small>{{ __('ui.pres.enter') }}</small>
                    </a>
                @else
                    <div class="pres-card">
                        <strong>{{ __('ui.pres.roles.'.$alias) }}</strong>
                        <small>{{ __('ui.pres.handoff_off') }}</small>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="hero-actions">
            <a class="button primary" href="{{ $pub('home') }}">{{ __('site.home.hero_title') }}</a>
            <a class="button ghost" href="{{ route('login',['locale'=>$locale]) }}">{{ __('site.links.login') }}</a>
            @if($canReseed)
                <form method="post" action="{{ route('public.pres.reseed') }}">
                    @csrf
                    <button class="button ghost" type="submit">{{ __('ui.pres.reset') }}</button>
                </form>
            @else
                <p class="boundary">{{ __('ui.pres.operator_only') }}</p>
            @endif
        </div>
    </div>
</section>
<section class="section white">
    <div class="shell">
        <h2>{{ __('ui.pres.gap') }}</h2>
        <div class="pres-table-wrap">
            <table class="pres-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Existing</th>
                        <th>Missing</th>
                        <th>Risk</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($gaps as $gap)
                        <tr>
                            <td>{{ $gap['area'] }}</td>
                            <td>{{ $gap['existing'] }}</td>
                            <td>{{ $gap['missing'] }}</td>
                            <td>{{ $gap['risk'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
</main>
@include('public.partials.footer')
</body>
</html>
