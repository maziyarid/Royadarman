<?php

namespace App\Http\Controllers;

use App\Models\MarketingPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

final class PublicPageController extends Controller
{
    public function home(string $locale): View
    {
        $page = $this->published('home', $locale);
        if ($page === null) {
            return view('public.home');
        }

        return view('public.marketing-page', $this->viewData($page, $locale, 'home'));
    }

    public function opg(string $locale): View
    {
        return $this->marketingView('services/opg', $locale, 'opg');
    }

    public function homeDentistry(string $locale): View
    {
        return $this->marketingView('services/home-dentistry', $locale, 'home_dentistry');
    }

    public function referrals(string $locale): View
    {
        return $this->marketingView('referrals', $locale, 'referrals');
    }

    public function contact(string $locale): View
    {
        return $this->marketingView('contact', $locale, 'contact');
    }

    public function sitemap(): Response
    {
        $urls = [];
        foreach (['fa', 'ar', 'en'] as $locale) {
            foreach (['public.home', 'public.opg', 'public.home-dentistry', 'public.referrals', 'public.contact'] as $route) {
                $urls[] = route($route, ['locale' => $locale]);
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function marketingView(string $slug, string $locale, string $fallbackKey): View
    {
        $page = $this->published($slug, $locale);
        if ($page !== null) {
            return view('public.marketing-page', $this->viewData($page, $locale, $fallbackKey));
        }

        return view('public.marketing-page', [
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
}
