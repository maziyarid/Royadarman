<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/fa/', 302);

Route::get('/up', fn () => response()->json(['status' => 'ok']));
Route::get('/{locale}/', fn () => response()->view('public.home')->header('Cache-Control', 'public, max-age=300'))
    ->whereIn('locale', ['fa', 'ar', 'en'])
    ->middleware(SetLocale::class)
    ->name('public.home');
