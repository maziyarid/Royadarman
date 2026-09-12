<?php

namespace App\Http\Controllers;

use App\Models\MarketingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PublicPageController extends Controller
{

    public function homePersian(): Response
    {
        app()->setLocale('fa');

        return $this->home('fa');
    }

    public function opgPersian(): Response
    {
        app()->setLocale('fa');

        return $this->opg('fa');
    }

    public function homeDentistryPersian(): Response
    {
        app()->setLocale('fa');

        return $this->homeDentistry('fa');
    }

    public function referralsPersian(): Response
    {
        app()->setLocale('fa');

        return $this->referrals('fa');
    }

    public function contactPersian(): Response
    {
        app()->setLocale('fa');

        return $this->contact('fa');
    }

    public function redirectPersianPrefix(Request $request, ?string $path = null): RedirectResponse
    {
        $target = '/'.ltrim((string) $path, '/');
        $target = $target === '/' ? '/' : rtrim($target, '/');
        $query = $request->getQueryString();

        return redirect()->to(url($target).($query ? '?'.$query : ''), 301);
    }

    public function home(string $locale): Response
    {
        $page = $this->published('home', $locale);
        if ($page === null) {
            return $this->publicResponse('public.home');
        }

        return $this->publicResponse('public.marketing-page', $this->viewData($page, $locale, 'home'));
    }

    public function opg(string $locale): Response
    {
        return $this->marketingResponse('services/opg', $locale, 'opg');
    }

    public function homeDentistry(string $locale): Response
    {
        return $this->marketingResponse('services/home-dentistry', $locale, 'home_dentistry');
    }

    public function referrals(string $locale): Response
    {
        return $this->marketingResponse('referrals', $locale, 'referrals');
    }

    public function contact(string $locale): Response
    {
        return $this->marketingResponse('contact', $locale, 'contact');
    }

    public function sitemap(): Response
    {
        $urls = [];
        foreach (['fa', 'ar', 'en'] as $locale) {
            foreach (['public.home', 'public.opg', 'public.home-dentistry', 'public.referrals', 'public.contact'] as $route) {
                $urls[] = $locale === 'fa'
                    ? route($route.'.fa')
                    : route($route, ['locale' => $locale]);
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function marketingResponse(string $slug, string $locale, string $fallbackKey): Response
    {
        $page = $this->published($slug, $locale);
        if ($page !== null) {
            return $this->publicResponse('public.marketing-page', $this->viewData($page, $locale, $fallbackKey));
        }

        return $this->publicResponse('public.marketing-page', [
            'page' => null,
            'locale' => $locale,
            'pageKey' => $fallbackKey,
            'title' => __('marketing.pages.'.$fallbackKey.'.title'),
            'excerpt' => __('marketing.pages.'.$fallbackKey.'.excerpt'),
            'body' => __('marketing.pages.'.$fallbackKey.'.body'),
            'metaTitle' => __('marketing.pages.'.$fallbackKey.'.meta_title'),
            'metaDescription' => __('marketing.pages.'.$fallbackKey.'.meta_description'),
        ]);
    }

    private function published(string $slug, string $locale): ?MarketingPage
    {
        return MarketingPage::query()
            ->where('slug', $slug)
            ->where('locale', $locale)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->first();
    }

    private function viewData(MarketingPage $page, string $locale, string $pageKey): array
    {
        return [
            'page' => $page,
            'locale' => $locale,
            'pageKey' => $pageKey,
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'body' => $page->body,
            'metaTitle' => $page->meta_title ?: $page->title,
            'metaDescription' => $page->meta_description ?: ($page->excerpt ?: $page->title),
        ];
    }

    private function publicResponse(string $view, array $data = []): Response
    {
        return response()->view($view, $data)
            ->header('Cache-Control', 'public, max-age=300')
            ->header('Vary', 'Accept-Language');
    }
}
