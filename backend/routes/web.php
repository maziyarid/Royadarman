<?php

use App\Http\Controllers\MarketingContentController;
use App\Http\Controllers\NetworkAdminController;
use App\Http\Controllers\PanelCaseController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PatientRequestController;
use App\Http\Controllers\PublicPageController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/fa/', 302);
Route::get('/up', fn () => response()->json(['status' => 'ok']));
Route::get('/sitemap.xml', [PublicPageController::class, 'sitemap'])->name('public.sitemap');

Route::prefix('{locale}')
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(SetLocale::class)
    ->group(function (): void {
        Route::get('/', [PublicPageController::class, 'home'])->name('public.home');
        Route::get('/services/opg', [PublicPageController::class, 'opg'])->name('public.opg');
        Route::get('/services/home-dentistry', [PublicPageController::class, 'homeDentistry'])->name('public.home-dentistry');
        Route::get('/referrals', [PublicPageController::class, 'referrals'])->name('public.referrals');
        Route::get('/contact', [PublicPageController::class, 'contact'])->name('public.contact');
        Route::get('/login', fn (string $locale) => auth()->check()
            ? redirect()->route('panel', ['locale' => $locale])
            : response()->view('auth.login', ['locale' => $locale])->header('Cache-Control', 'private, no-store'))
            ->name('login');

        Route::middleware('auth')->group(function (): void {
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
