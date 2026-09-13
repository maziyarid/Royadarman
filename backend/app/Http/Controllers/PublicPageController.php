<?php

namespace App\Http\Controllers;

use App\Models\MarketingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PublicPageController extends Controller
{
    private const PUBLIC_ROUTES = ['home' => 'public.home', 'services' => 'public.services', 'opg' => 'public.opg', 'home_dentistry' => 'public.home-dentistry', 'referrals' => 'public.referrals', 'how' => 'public.how', 'about' => 'public.about', 'contact' => 'public.contact', 'privacy' => 'public.privacy', 'faq' => 'public.faq'];

    public function homePersian(): Response
    {
        return $this->fa(fn () => $this->home('fa'));
    }

    public function servicesPersian(): Response
    {
        return $this->fa(fn () => $this->services('fa'));
    }

    public function opgPersian(): Response
    {
        return $this->fa(fn () => $this->opg('fa'));
    }

    public function homeDentistryPersian(): Response
    {
        return $this->fa(fn () => $this->homeDentistry('fa'));
    }

    public function referralsPersian(): Response
    {
        return $this->fa(fn () => $this->referrals('fa'));
    }

    public function howPersian(): Response
    {
        return $this->fa(fn () => $this->how('fa'));
    }

    public function aboutPersian(): Response
    {
        return $this->fa(fn () => $this->about('fa'));
    }

    public function contactPersian(): Response
    {
        return $this->fa(fn () => $this->contact('fa'));
    }

    public function privacyPersian(): Response
    {
        return $this->fa(fn () => $this->privacy('fa'));
    }

    public function faqPersian(): Response
    {
        return $this->fa(fn () => $this->faq('fa'));
    }

    public function redirectPersianPrefix(Request $request, ?string $path = null): RedirectResponse
    {
        $target = '/'.ltrim((string) $path, '/');
        $target = $target === '/' ? '/' : rtrim($target, '/');
        $q = $request->getQueryString();

        return redirect()->to(url($target).($q ? '?'.$q : ''), 301);
    }

    public function home(string $locale): Response
    {
        $page = $this->published('home', $locale);

        return $page ? $this->publicResponse('public.marketing-page', $this->viewData($page, $locale, 'home')) : $this->publicResponse('public.home', ['locale' => $locale, 'pageKey' => 'home']);
    }

    public function services(string $locale): Response
    {
        return $this->staticPage('services', $locale);
    }

    public function how(string $locale): Response
    {
        return $this->staticPage('how', $locale);
    }

    public function about(string $locale): Response
    {
        return $this->staticPage('about', $locale);
    }

    public function privacy(string $locale): Response
    {
        return $this->staticPage('privacy', $locale);
    }

    public function faq(string $locale): Response
    {
        return $this->staticPage('faq', $locale);
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
            foreach (self::PUBLIC_ROUTES as $route) {
                $urls[] = $locale === 'fa' ? route($route.'.fa') : route($route, ['locale' => $locale]);
            }
        } $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</loc></url>\n";
        } $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=300']);
    }

    private function staticPage(string $key, string $locale): Response
    {
        $content = trans('site.pages.'.$key);
        abort_unless(is_array($content), 404);

        return $this->publicResponse('public.static-page', ['pageKey' => $key, 'locale' => $locale, 'content' => $content]);
    }

    private function marketingResponse(string $slug, string $locale, string $key): Response
    {
        $page = $this->published($slug, $locale);
        if ($page) {
            return $this->publicResponse('public.marketing-page', $this->viewData($page, $locale, $key));
        }

return $this->publicResponse('public.marketing-page', ['page' => null, 'locale' => $locale, 'pageKey' => $key, 'title' => __('marketing.pages.'.$key.'.title'), 'excerpt' => __('marketing.pages.'.$key.'.excerpt'), 'body' => __('marketing.pages.'.$key.'.body'), 'metaTitle' => __('marketing.pages.'.$key.'.meta_title'), 'metaDescription' => __('marketing.pages.'.$key.'.meta_description')]);
    }

    private function published(string $slug, string $locale): ?MarketingPage
    {
        return MarketingPage::query()->where('slug', $slug)->where('locale', $locale)->where('status', 'published')->whereNotNull('published_at')->first();
    }

    private function viewData(MarketingPage $p, string $locale, string $key): array
    {
        return ['page' => $p, 'locale' => $locale, 'pageKey' => $key, 'title' => $p->title, 'excerpt' => $p->excerpt, 'body' => $p->body, 'metaTitle' => $p->meta_title ?: $p->title, 'metaDescription' => $p->meta_description ?: ($p->excerpt ?: $p->title)];
    }

    private function fa(callable $cb): Response
    {
        app()->setLocale('fa');

        return $cb();
    }

    private function publicResponse(string $view, array $data = []): Response
    {
        return response()->view($view,$data)->header('Cache-Control','public, max-age=300')->header('Vary','Accept-Language');
    }
}
