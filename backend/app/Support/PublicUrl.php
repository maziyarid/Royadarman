<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

final class PublicUrl
{
    public static function to(string $key, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $name = match ($key) {
            'blog.index', 'blog' => 'public.blog.index',
            default => str_starts_with($key, 'public.') ? $key : 'public.'.$key,
        };
        if ($locale === 'fa' && Route::has($name.'.fa')) {
            return route($name.'.fa');
        }
        if (Route::has($name)) {
            return route($name, ['locale' => $locale]);
        }

        return $locale === 'fa' ? url('/') : url('/'.$locale);
    }

    public static function currentKey(): string
    {
        $name = (string) Route::currentRouteName();
        $name = preg_replace('/\.fa$/', '', $name) ?: $name;

        return match (true) {
            str_contains($name, 'blog') => 'blog.index',
            str_contains($name, 'home-dentistry') => 'home-dentistry',
            str_contains($name, 'opg') => 'opg',
            str_contains($name, 'referrals') => 'referrals',
            str_contains($name, 'services') => 'services',
            str_contains($name, 'how') => 'how',
            str_contains($name, 'about') => 'about',
            str_contains($name, 'contact') => 'contact',
            str_contains($name, 'privacy') => 'privacy',
            str_contains($name, 'faq') => 'faq',
            default => 'home',
        };
    }
}
