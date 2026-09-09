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
    ->middleware(['web', SetLocale::class, 'auth'])
    ->name('dashboard');

Route::get('/admin', fn () => redirect()->route('admin.cms.posts.index'))->middleware(['staff']);
