<?php

use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/fa/', 302);

Route::get('/up', fn () => response()->json(['status' => 'ok']));
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-{locale}.xml', [SitemapController::class, 'locale'])->whereIn('locale', ['fa', 'ar', 'en'])->name('sitemap.locale');
Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::get('/{locale}/', fn () => response()->view('public.home')->header('Cache-Control', 'public, max-age=300'))
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(SetLocale::class)
    ->name('public.home');

Route::get('/{locale}/blog/{slug}', [BlogController::class, 'show'])
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(SetLocale::class)
    ->name('public.blog.show');
