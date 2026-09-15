<?php

namespace App\Http\Controllers;

use App\Models\MarketingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PublicPageController extends Controller
{
    private const PHOTOS = [
        'services' => 'coord',
        'how' => 'support',
        'about' => 'about',
        'contact' => 'support',
        'opg' => 'opg',
        'home_dentistry' => 'home',
        'referrals' => 'coord',
    ];

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

        return $page
            ? $this->publicResponse('public.marketing-page', $this->viewData($page, $locale, 'home'))
            : $this->publicResponse('public.home', ['locale' => $locale, 'pageKey' => 'home']);
    }

    public function services(string $locale): Response
    {
        $content = trans('site.pages.services');
        abort_unless(is_array($content), 404);

        return $this->story($locale, 'services', $content, [
            'photo' => self::PHOTOS['services'],
            'serviceCards' => [
                ['key' => 'referrals', 'photo' => 'coord', 'title' => $content['services'][0]['title'], 'text' => $content['services'][0]['text']],
                ['key' => 'home-dentistry', 'photo' => 'home', 'title' => $content['services'][1]['title'], 'text' => $content['services'][1]['text']],
                ['key' => 'opg', 'photo' => 'opg', 'title' => $content['services'][2]['title'], 'text' => $content['services'][2]['text']],
            ],
            'related' => $this->related(['opg', 'home-dentistry', 'referrals']),
            'heroActions' => [
                ['href' => route('login', ['locale' => $locale]), 'label' => __('site.cta'), 'style' => 'primary'],
                ['href' => $this->url('how', $locale), 'label' => __('site.nav.how'), 'style' => 'ghost'],
            ],
        ]);
    }

    public function how(string $locale): Response
    {
        return $this->staticStory('how', $locale, ['photo' => self::PHOTOS['how'], 'related' => $this->related(['services', 'contact'])]);
    }

    public function about(string $locale): Response
    {
        return $this->staticStory('about', $locale, ['photo' => self::PHOTOS['about'], 'related' => $this->related(['how', 'contact'])]);
    }

    public function privacy(string $locale): Response
    {
        return $this->staticStory('privacy', $locale, ['related' => $this->related(['faq', 'contact'])]);
    }

    public function faq(string $locale): Response
    {
        $content = trans('site.pages.faq');
        abort_unless(is_array($content), 404);

        return $this->story($locale, 'faq', $content, [
            'faqs' => __('site.faqs'),
            'related' => $this->related(['services', 'contact']),
        ]);
    }

    public function opg(string $locale): Response
    {
        return $this->serviceStory('services/opg', $locale, 'opg', ['home-dentistry', 'referrals']);
    }

    public function homeDentistry(string $locale): Response
    {
        return $this->serviceStory('services/home-dentistry', $locale, 'home_dentistry', ['referrals', 'opg']);
    }

    public function referrals(string $locale): Response
    {
        return $this->serviceStory('referrals', $locale, 'referrals', ['home-dentistry', 'opg']);
    }

    public function contact(string $locale): Response
    {
        return $this->serviceStory('contact', $locale, 'contact', ['faq', 'privacy']);
    }

    private function staticStory(string $key, string $locale, array $extra = []): Response
    {
        $content = trans('site.pages.'.$key);
        abort_unless(is_array($content), 404);

        return $this->story($locale, $key, $content, $extra);
    }

    private function serviceStory(string $slug, string $locale, string $key, array $relatedKeys): Response
    {
        $page = $this->published($slug, $locale);
        $details = trans('site.service_pages.'.$key);
        $marketing = trans('marketing.pages.'.$key);
        $title = is_array($marketing) ? ($page?->title ?: $marketing['title']) : ($page?->title ?: $key);
        $excerpt = $page?->excerpt ?: (is_array($marketing) ? ($marketing['excerpt'] ?? '') : '');
        $body = $page?->body ?: (is_array($marketing) ? ($marketing['body'] ?? '') : '');
        $metaTitle = $page?->meta_title ?: (is_array($marketing) ? ($marketing['meta_title'] ?? $title) : $title);
        $metaDescription = $page?->meta_description ?: (is_array($marketing) ? ($marketing['meta_description'] ?? $excerpt) : $excerpt);

        return $this->publicResponse('public.story', [
            'pageKey' => $key === 'home_dentistry' ? 'home-dentistry' : $key,
            'locale' => $locale,
            'title' => $title,
            'lead' => $excerpt,
            'excerpt' => $excerpt,
            'body' => $body,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'photo' => self::PHOTOS[$key] ?? null,
            'overviewTitle' => is_array($details) ? ($details['overview_title'] ?? $title) : $title,
            'points' => is_array($details) ? ($details['points'] ?? []) : [],
            'stepsTitle' => is_array($details) ? ($details['steps_title'] ?? '') : '',
            'steps' => is_array($details) ? ($details['steps'] ?? []) : [],
            'noteTitle' => is_array($details) ? ($details['note_title'] ?? null) : null,
            'note' => is_array($details) ? ($details['note'] ?? null) : null,
            'faqs' => in_array($key, ['opg', 'home_dentistry', 'referrals'], true) ? trans('site.faqs') : [],
            'related' => $this->related($relatedKeys),
            'heroActions' => [
                ['href' => route('login', ['locale' => $locale]), 'label' => __('site.cta'), 'style' => 'primary'],
                ['href' => $this->url('how', $locale), 'label' => __('site.nav.how'), 'style' => 'ghost'],
            ],
            'schemaType' => 'WebPage',
        ]);
    }

    private function story(string $locale, string $key, array $content, array $extra = []): Response
    {
        return $this->publicResponse('public.story', array_merge([
            'pageKey' => $key,
            'locale' => $locale,
            'title' => $content['title'],
            'lead' => $content['lead'] ?? '',
            'metaTitle' => $content['meta_title'] ?? $content['title'],
            'metaDescription' => $content['meta_description'] ?? ($content['lead'] ?? ''),
            'sections' => $content['sections'] ?? [],
            'faqTitle' => $content['faq_title'] ?? null,
            'schemaType' => 'WebPage',
        ], $extra));
    }

    /** @param list<string> $keys */
    private function related(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $page = $key === 'home-dentistry' ? 'home_dentistry' : $key;
            $content = trans('site.pages.'.$page);
            if (! is_array($content)) {
                $content = trans('marketing.pages.'.$page);
            }
            if (! is_array($content)) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'title' => $content['title'] ?? $key,
                'text' => $content['lead'] ?? ($content['excerpt'] ?? ''),
            ];
        }

        return $out;
    }

    private function published(string $slug, string $locale): ?MarketingPage
    {
        return MarketingPage::query()->where('slug', $slug)->where('locale', $locale)->where('status', 'published')->whereNotNull('published_at')->first();
    }

    private function viewData(MarketingPage $p, string $locale, string $key): array
    {
        return ['page' => $p, 'locale' => $locale, 'pageKey' => $key, 'title' => $p->title, 'excerpt' => $p->excerpt, 'body' => $p->body, 'metaTitle' => $p->meta_title ?: $p->title, 'metaDescription' => $p->meta_description ?: ($p->excerpt ?: $p->title)];
    }

    private function url(string $key, string $locale): string
    {
        return $locale === 'fa' ? route('public.'.$key.'.fa') : route('public.'.$key, ['locale' => $locale]);
    }

    private function fa(callable $cb): Response
    {
        app()->setLocale('fa');

        return $cb();
    }

    private function publicResponse(string $view, array $data = []): Response
    {
        return response()->view($view, $data)->header('Cache-Control', 'public, max-age=300')->header('Vary', 'Accept-Language');
    }
}
