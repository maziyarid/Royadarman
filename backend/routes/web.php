<?php

use App\Http\Controllers\DemoPanelAccessController;
use App\Http\Controllers\MarketingContentController;
use App\Http\Controllers\NetworkAdminController;
use App\Http\Controllers\PanelCaseController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PatientRequestController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Web\Admin\AdminCmsController;
use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\CmsMediaServeController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PublicRedirectController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/up', fn () => response()->json(['status' => 'ok']));
Route::get('/__panel-test/{role}', DemoPanelAccessController::class)
    ->whereIn('role', ['admin', 'client', 'clinic'])
    ->middleware('signed')
    ->name('demo.panel.access');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-{locale}.xml', [SitemapController::class, 'locale'])->whereIn('locale', ['fa', 'ar', 'en'])->name('sitemap.locale');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/cms-media/{media}', [CmsMediaServeController::class, 'show'])->name('cms.media.serve');

Route::get('/', [PublicPageController::class, 'homePersian'])->name('public.home.fa');
Route::get('/services', [PublicPageController::class, 'servicesPersian'])->name('public.services.fa');
Route::get('/services/opg', [PublicPageController::class, 'opgPersian'])->name('public.opg.fa');
Route::get('/services/home-dentistry', [PublicPageController::class, 'homeDentistryPersian'])->name('public.home-dentistry.fa');
Route::get('/referrals', [PublicPageController::class, 'referralsPersian'])->name('public.referrals.fa');
Route::get('/how-it-works', [PublicPageController::class, 'howPersian'])->name('public.how.fa');
Route::get('/about', [PublicPageController::class, 'aboutPersian'])->name('public.about.fa');
Route::get('/contact', [PublicPageController::class, 'contactPersian'])->name('public.contact.fa');
Route::get('/privacy', [PublicPageController::class, 'privacyPersian'])->name('public.privacy.fa');
Route::get('/faq', [PublicPageController::class, 'faqPersian'])->name('public.faq.fa');
Route::get('/blog', [BlogController::class, 'indexPersian'])->name('public.blog.index.fa');
Route::get('/blog/{slug}', [BlogController::class, 'showPersian'])->name('public.blog.show.fa');

Route::prefix('{locale}')->whereIn('locale', ['ar', 'en'])->middleware(SetLocale::class)->group(function (): void {
    Route::get('/', [PublicPageController::class, 'home'])->name('public.home');
    Route::get('/services', [PublicPageController::class, 'services'])->name('public.services');
    Route::get('/services/opg', [PublicPageController::class, 'opg'])->name('public.opg');
    Route::get('/services/home-dentistry', [PublicPageController::class, 'homeDentistry'])->name('public.home-dentistry');
    Route::get('/referrals', [PublicPageController::class, 'referrals'])->name('public.referrals');
    Route::get('/how-it-works', [PublicPageController::class, 'how'])->name('public.how');
    Route::get('/about', [PublicPageController::class, 'about'])->name('public.about');
    Route::get('/contact', [PublicPageController::class, 'contact'])->name('public.contact');
    Route::get('/privacy', [PublicPageController::class, 'privacy'])->name('public.privacy');
    Route::get('/faq', [PublicPageController::class, 'faq'])->name('public.faq');
    Route::get('/blog', [BlogController::class, 'index'])->name('public.blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('public.blog.show');
});

Route::prefix('{locale}')->whereIn('locale', ['fa', 'ar', 'en'])->middleware(SetLocale::class)->group(function (): void {
    Route::get('/login', fn (string $locale) => auth()->check()
        ? redirect()->route('panel', ['locale' => $locale])
        : response()->view('auth.login', ['locale' => $locale, 'intakeEnabled' => (bool) config('royadarman.intake_enabled')])->header('Cache-Control', 'private, no-store'))
        ->name('login');
    Route::middleware(['auth', EnsureActiveUser::class])->group(function (): void {
        Route::get('/panel', PanelController::class)->name('panel');
        Route::get('/panel/cases/new', [PatientRequestController::class, 'create'])->name('patient.request.create');
        Route::get('/panel/cases/{case}', [PanelCaseController::class, 'show'])->name('panel.case');
        Route::prefix('panel/marketing')->group(function (): void {
            Route::get('/', [MarketingContentController::class, 'index'])->name('marketing.index');
            Route::get('/create', [MarketingContentController::class, 'create'])->name('marketing.create');
            Route::post('/', [MarketingContentController::class, 'store'])->name('marketing.store');
            Route::get('/{page}/edit', [MarketingContentController::class, 'edit'])->name('marketing.edit');
            Route::put('/{page}', [MarketingContentController::class, 'update'])->name('marketing.update');
            Route::post('/{page}/publish', [MarketingContentController::class, 'publish'])->name('marketing.publish');
        });
        Route::prefix('panel/network')->group(function (): void {
            Route::get('/', [NetworkAdminController::class, 'index'])->name('network.index');
            Route::post('/clinics', [NetworkAdminController::class, 'storeClinic'])->name('network.clinic.store');
            Route::put('/clinics/{clinic}', [NetworkAdminController::class, 'updateClinic'])->name('network.clinic.update');
            Route::post('/practitioners/{user}', [NetworkAdminController::class, 'savePractitioner'])->whereNumber('user')->name('network.practitioner.save');
            Route::post('/memberships', [NetworkAdminController::class, 'storeMembership'])->name('network.membership.store');
            Route::delete('/memberships/{membership}', [NetworkAdminController::class, 'revokeMembership'])->name('network.membership.revoke');
        });
    });
});

Route::get('/{locale}/blog/{slug}/preview', [BlogController::class, 'preview'])
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(['signed', 'auth', SetLocale::class])
    ->name('public.blog.preview');

Route::middleware(['staff'])->prefix('/admin/cms')->name('admin.cms.')->group(function (): void {
    Route::get('/posts', [AdminCmsController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [AdminCmsController::class, 'create'])->name('posts.create');
    Route::post('/posts', [AdminCmsController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}', [AdminCmsController::class, 'show'])->name('posts.show');
    Route::patch('/posts/{post}', [AdminCmsController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/publish', [AdminCmsController::class, 'publish'])->name('posts.publish');
    Route::post('/posts/{post}/unpublish', [AdminCmsController::class, 'unpublish'])->name('posts.unpublish');
    Route::delete('/posts/{post}', [AdminCmsController::class, 'destroy'])->name('posts.destroy');

    Route::get('/categories', [AdminCmsController::class, 'categoriesIndex'])->name('categories.index');
    Route::get('/categories/create', [AdminCmsController::class, 'categoriesCreate'])->name('categories.create');
    Route::post('/categories', [AdminCmsController::class, 'categoriesStore'])->name('categories.store');
    Route::get('/categories/{category}/edit', [AdminCmsController::class, 'categoriesEdit'])->name('categories.edit');
    Route::patch('/categories/{category}', [AdminCmsController::class, 'categoriesUpdate'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCmsController::class, 'categoriesDestroy'])->name('categories.destroy');

    Route::get('/tags', [AdminCmsController::class, 'tagsIndex'])->name('tags.index');
    Route::post('/tags', [AdminCmsController::class, 'tagsStore'])->name('tags.store');
    Route::delete('/tags/{tag}', [AdminCmsController::class, 'tagsDestroy'])->name('tags.destroy');

    Route::get('/media', [AdminCmsController::class, 'mediaIndex'])->name('media.index');
    Route::post('/media', [AdminCmsController::class, 'mediaStore'])->name('media.store');
    Route::delete('/media/{media}', [AdminCmsController::class, 'mediaDestroy'])->name('media.destroy');

    Route::get('/menus', [AdminCmsController::class, 'menusIndex'])->name('menus.index');
    Route::post('/menus', [AdminCmsController::class, 'menusStore'])->name('menus.store');
    Route::delete('/menus/{menu}', [AdminCmsController::class, 'menusDestroy'])->name('menus.destroy');
    Route::post('/menus/{menu}/items', [AdminCmsController::class, 'menuItemsStore'])->name('menus.items.store');
    Route::delete('/menus/{menu}/items/{item}', [AdminCmsController::class, 'menuItemsDestroy'])->name('menus.items.destroy');

    Route::get('/redirects', [AdminCmsController::class, 'redirectsIndex'])->name('redirects.index');
    Route::post('/redirects', [AdminCmsController::class, 'redirectsStore'])->name('redirects.store');
    Route::delete('/redirects/{redirect}', [AdminCmsController::class, 'redirectsDestroy'])->name('redirects.destroy');

    Route::get('/comments', [AdminCmsController::class, 'commentsIndex'])->name('comments.index');
    Route::post('/comments/{comment}/approve', [AdminCmsController::class, 'commentsApprove'])->name('comments.approve');
    Route::post('/comments/{comment}/spam', [AdminCmsController::class, 'commentsMarkSpam'])->name('comments.spam');
    Route::delete('/comments/{comment}', [AdminCmsController::class, 'commentsDestroy'])->name('comments.destroy');

    Route::get('/seo', [AdminCmsController::class, 'seoIndex'])->name('seo.index');
    Route::get('/seo/{post}/edit', [AdminCmsController::class, 'seoEdit'])->name('seo.edit');
    Route::patch('/seo/{post}', [AdminCmsController::class, 'seoUpdate'])->name('seo.update');
});

Route::get('/dashboard', fn () => redirect('/fa/dashboard', 302));
Route::get('/{locale}/dashboard', [DashboardController::class, 'show'])
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(['web', SetLocale::class, 'auth', EnsureActiveUser::class])
    ->name('dashboard');
Route::get('/admin', fn () => redirect()->route('admin.cms.posts.index'))->middleware(['staff']);

Route::get('/fa/{path?}', [PublicPageController::class, 'redirectPersianPrefix'])->where('path', '.*')->name('public.fa-redirect');

// CMS-managed public content comes after explicit product routes so it cannot
// shadow the core services/about/contact/privacy/FAQ information architecture.
Route::get('/services/{slug}', [PageController::class, 'showPersianService'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('public.service.show.fa');
Route::get('/{slug}', [PageController::class, 'showPersianPage'])
    ->where('slug', '^(?!(?:fa|ar|en|admin|dashboard|up|sitemap|robots|cms-media)$)[a-z0-9\-]+$')
    ->name('public.page.show.fa');
Route::get('/{locale}/services/{slug}', [PageController::class, 'showService'])
    ->whereIn('locale', ['ar', 'en'])
    ->where('slug', '[a-z0-9\-]+')
    ->middleware(SetLocale::class)
    ->name('public.service.show');
Route::get('/{locale}/{slug}', [PageController::class, 'showPage'])
    ->whereIn('locale', ['ar', 'en'])
    ->where('slug', '[a-z0-9\-]+')
    ->middleware(SetLocale::class)
    ->name('public.page.show');

Route::fallback([PublicRedirectController::class, 'resolve']);
