<?php

use App\Http\Controllers\DemoPanelAccessController;
use App\Http\Controllers\MarketingContentController;
use App\Http\Controllers\NetworkAdminController;
use App\Http\Controllers\PanelCaseController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PatientRequestController;
use App\Http\Controllers\PresentationPortalController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\Web\Admin\AdminCmsController;
use App\Http\Controllers\Web\Admin\AdministratorController;
use App\Http\Controllers\Web\Admin\IntegrationSettingsController;
use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\CaseQueueController;
use App\Http\Controllers\Web\CmsMediaServeController;
use App\Http\Controllers\Web\CoordinationTaskController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HomeServiceWorkspaceController;
use App\Http\Controllers\Web\LaunchReadinessController;
use App\Http\Controllers\Web\NotificationDeliveryController;
use App\Http\Controllers\Web\OperationsAnalyticsController;
use App\Http\Controllers\Web\OperationsCalendarController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PolicyManagementController;
use App\Http\Controllers\Web\ProfileWorkspaceController;
use App\Http\Controllers\Web\PublicRedirectController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\SupportWorkspaceController;
use App\Http\Controllers\Web\WorkspaceSearchController;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRecentAuthentication;
use App\Http\Middleware\RestrictPanelDemoSession;
use App\Http\Middleware\SetLocale;
use App\Support\PanelDemoRegistry;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/up', fn () => response()->json(['status' => 'ok']));
Route::get('/manifest.webmanifest', fn () => response()->file(
    resource_path('pwa/manifest.webmanifest'),
    [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ],
))->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    RestrictPanelDemoSession::class,
])->name('pwa.manifest');

Route::get('/sw.js', fn () => response()->file(
    resource_path('pwa/sw.js'),
    [
        'Content-Type' => 'application/javascript; charset=utf-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Service-Worker-Allowed' => '/',
    ],
))->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    RestrictPanelDemoSession::class,
])->name('pwa.service-worker');

Route::get('/offline.html', fn () => response()->file(
    resource_path('pwa/offline.html'),
    [
        'Content-Type' => 'text/html; charset=utf-8',
        'Cache-Control' => 'public, max-age=86400',
    ],
))->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    RestrictPanelDemoSession::class,
])->name('pwa.offline');

Route::get('/__panel-test/{role}', DemoPanelAccessController::class)
    ->whereIn('role', PanelDemoRegistry::aliases())
    ->middleware('signed')
    ->name('demo.panel.access');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-{locale}.xml', [SitemapController::class, 'locale'])->whereIn('locale', ['fa', 'ar', 'en'])->name('sitemap.locale');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/cms-media/{media}', [CmsMediaServeController::class, 'show'])->name('cms.media.serve');
Route::get('/pres', [PresentationPortalController::class, 'show'])->name('public.pres');
Route::post('/pres/reseed', [PresentationPortalController::class, 'reseed'])
    ->middleware(['auth', EnsureActiveUser::class])
    ->name('public.pres.reseed');

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
        : response()->view('auth.login', ['locale' => $locale, 'intakeEnabled' => (bool) config('royadarman.intake_enabled'), 'otpLength' => (int) config('royadarman.sms.otp.length', 6)])->header('Cache-Control', 'private, no-store'))
        ->name('login');
    Route::middleware(['auth', EnsureActiveUser::class])->group(function (): void {
        Route::get('/panel', PanelController::class)->name('panel');
        Route::get('/panel/cases/new', [PatientRequestController::class, 'create'])->name('patient.request.create');
        Route::get('/panel/cases/{case}', [PanelCaseController::class, 'show'])->name('panel.case');
        Route::get('/panel/search', [WorkspaceSearchController::class, 'index'])->name('panel.search.index');
        Route::get('/panel/policies', [PolicyManagementController::class, 'index'])->name('panel.policies.index');
        Route::post('/panel/policies', [PolicyManagementController::class, 'store'])->name('panel.policies.store');
        Route::patch('/panel/policies/{policy}', [PolicyManagementController::class, 'update'])->name('panel.policies.update');
        Route::delete('/panel/policies/{policy}', [PolicyManagementController::class, 'destroy'])->name('panel.policies.destroy');
        Route::post('/panel/policies/{policy}/publish', [PolicyManagementController::class, 'publish'])
            ->middleware(EnsureRecentAuthentication::class)
            ->name('panel.policies.publish');
        Route::get('/panel/analytics', [OperationsAnalyticsController::class, 'index'])->name('panel.analytics.index');
        Route::get('/panel/calendar', [OperationsCalendarController::class, 'index'])->name('panel.calendar.index');
        Route::get('/panel/launch-readiness', [LaunchReadinessController::class, 'index'])->name('panel.launch-readiness.index');
        Route::get('/panel/deliveries', [NotificationDeliveryController::class, 'index'])->name('panel.deliveries.index');
        Route::post('/panel/launch-readiness/acknowledge', [LaunchReadinessController::class, 'acknowledge'])->middleware(EnsureRecentAuthentication::class)->name('panel.launch-readiness.acknowledge');
        Route::get('/panel/cases', [CaseQueueController::class, 'index'])->name('panel.cases.index');
        Route::get('/panel/tasks', [CoordinationTaskController::class, 'index'])->name('panel.tasks.index');
        Route::post('/panel/tasks', [CoordinationTaskController::class, 'store'])->name('panel.tasks.store');
        Route::patch('/panel/tasks/{task}', [CoordinationTaskController::class, 'update'])->name('panel.tasks.update');
        Route::delete('/panel/tasks/{task}', [CoordinationTaskController::class, 'destroy'])->name('panel.tasks.destroy');
        Route::get('/panel/support', [SupportWorkspaceController::class, 'index'])->name('panel.support.index');
        Route::post('/panel/support', [SupportWorkspaceController::class, 'store'])->name('panel.support.store');
        Route::get('/panel/support/{conversation}', [SupportWorkspaceController::class, 'show'])->name('panel.support.show');
        Route::post('/panel/support/{conversation}/messages', [SupportWorkspaceController::class, 'reply'])->name('panel.support.reply');
        Route::post('/panel/support/{conversation}/internal-notes', [SupportWorkspaceController::class, 'internalNote'])->name('panel.support.internal-note');
        Route::post('/panel/support/{conversation}/priority', [SupportWorkspaceController::class, 'priority'])->name('panel.support.priority');
        Route::post('/panel/support/{conversation}/status', [SupportWorkspaceController::class, 'status'])->name('panel.support.status');
        Route::post('/panel/support/{conversation}/assign', [SupportWorkspaceController::class, 'assign'])->name('panel.support.assign');
        Route::get('/panel/home-service', [HomeServiceWorkspaceController::class, 'index'])->name('panel.home-service.index');
        Route::get('/panel/home-service/{homeService}', [HomeServiceWorkspaceController::class, 'show'])->name('panel.home-service.show');
        Route::post('/panel/home-service/{homeService}/transition', [HomeServiceWorkspaceController::class, 'transition'])->name('panel.home-service.transition');
        Route::post('/panel/home-service/{homeService}/confirm', [HomeServiceWorkspaceController::class, 'confirm'])->name('panel.home-service.confirm');
        Route::get('/panel/profile', [ProfileWorkspaceController::class, 'show'])->name('panel.profile');
        Route::patch('/panel/profile', [ProfileWorkspaceController::class, 'update'])->name('panel.profile.update');
        Route::post('/panel/profile/credentials', [ProfileWorkspaceController::class, 'updateCredentials'])->middleware(EnsureRecentAuthentication::class)->name('panel.profile.credentials');
        Route::get('/panel/integrations', [IntegrationSettingsController::class, 'index'])->name('integrations.index');
        Route::put('/panel/integrations', [IntegrationSettingsController::class, 'update'])->name('integrations.update');
        Route::get('/panel/administrators', [AdministratorController::class, 'index'])->name('administrators.index');
        Route::post('/panel/administrators', [AdministratorController::class, 'store'])->name('administrators.store');
        Route::patch('/panel/administrators/{user}', [AdministratorController::class, 'update'])->name('administrators.update');
        Route::post('/panel/administrators/{user}/reset-mfa', [AdministratorController::class, 'resetMfa'])->name('administrators.mfa.reset');
        Route::post('/panel/administrators/{user}/revoke-sessions', [AdministratorController::class, 'revokeSessions'])->name('administrators.sessions.revoke');
        Route::post('/panel/profile/security/totp/start', [ProfileWorkspaceController::class, 'startTotp'])->middleware(EnsureRecentAuthentication::class)->name('panel.profile.security.totp.start');
        Route::post('/panel/profile/security/totp/confirm', [ProfileWorkspaceController::class, 'confirmTotp'])->middleware(EnsureRecentAuthentication::class)->name('panel.profile.security.totp.confirm');
        Route::post('/panel/profile/security/totp/cancel', [ProfileWorkspaceController::class, 'cancelTotp'])->middleware(EnsureRecentAuthentication::class)->name('panel.profile.security.totp.cancel');
        Route::post('/panel/profile/security/totp/disable', [ProfileWorkspaceController::class, 'disableTotp'])->middleware(EnsureRecentAuthentication::class)->name('panel.profile.security.totp.disable');
        Route::delete('/panel/profile/sessions/{session}', [ProfileWorkspaceController::class, 'revokeSession'])->name('panel.profile.sessions.revoke');
        Route::post('/panel/profile/sessions/revoke-others', [ProfileWorkspaceController::class, 'revokeOthers'])->name('panel.profile.sessions.revoke_others');
        Route::post('/panel/profile/sessions/revoke-all', [ProfileWorkspaceController::class, 'revokeAll'])->name('panel.profile.sessions.revoke_all');
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
            Route::post('/capabilities', [NetworkAdminController::class, 'saveCapability'])->name('network.capability.save');
        });
    });
});

Route::get('/{locale}/blog/{slug}/preview', [BlogController::class, 'preview'])
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(['signed', 'auth', SetLocale::class])
    ->name('public.blog.preview');

Route::middleware(['staff'])->prefix('/admin/cms')->name('admin.cms.')->group(function (): void {
    Route::get('/', [AdminCmsController::class, 'dashboard'])->name('dashboard');
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
    Route::patch('/media/{media}', [AdminCmsController::class, 'mediaUpdate'])->name('media.update');
    Route::delete('/media/{media}', [AdminCmsController::class, 'mediaDestroy'])->name('media.destroy');

    Route::get('/menus', [AdminCmsController::class, 'menusIndex'])->name('menus.index');
    Route::post('/menus', [AdminCmsController::class, 'menusStore'])->name('menus.store');
    Route::patch('/menus/{menu}', [AdminCmsController::class, 'menusUpdate'])->name('menus.update');
    Route::delete('/menus/{menu}', [AdminCmsController::class, 'menusDestroy'])->name('menus.destroy');
    Route::post('/menus/{menu}/items', [AdminCmsController::class, 'menuItemsStore'])->name('menus.items.store');
    Route::patch('/menus/{menu}/items/{item}', [AdminCmsController::class, 'menuItemsUpdate'])->name('menus.items.update');
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
Route::get('/admin', fn () => redirect()->route('admin.cms.dashboard'))->middleware(['staff']);

Route::get('/fa/{path?}', [PublicPageController::class, 'redirectPersianPrefix'])->where('path', '.*')->name('public.fa-redirect');

// CMS-managed public content comes after explicit product routes so it cannot
// shadow the core services/about/contact/privacy/FAQ information architecture.
Route::get('/services/{slug}', [PageController::class, 'showPersianService'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('public.service.show.fa');
Route::get('/{slug}', [PageController::class, 'showPersianPage'])
    ->where('slug', '^(?!(?:fa|ar|en|admin|dashboard|up|sitemap|robots|cms-media|pres)$)[a-z0-9\-]+$')
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
