<?php

use App\Http\Controllers\Web\Admin\AdminCmsController;
use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\DashboardController;
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

Route::middleware(['staff'])->prefix('/admin/cms')->name('admin.cms.')->group(function (): void {
    Route::get('/posts', [AdminCmsController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [AdminCmsController::class, 'create'])->name('posts.create');
    Route::post('/posts', [AdminCmsController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}', [AdminCmsController::class, 'show'])->name('posts.show');
    Route::patch('/posts/{post}', [AdminCmsController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/publish', [AdminCmsController::class, 'publish'])->name('posts.publish');
    Route::post('/posts/{post}/unpublish', [AdminCmsController::class, 'unpublish'])->name('posts.unpublish');
    Route::delete('/posts/{post}', [AdminCmsController::class, 'destroy'])->name('posts.destroy');
});

Route::get('/dashboard', fn () => redirect('/fa/dashboard', 302));

Route::get('/{locale}/dashboard', [DashboardController::class, 'show'])
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(['web', SetLocale::class, 'auth'])
    ->name('dashboard');

Route::get('/admin', fn () => redirect()->route('admin.cms.posts.index'))->middleware(['staff']);
