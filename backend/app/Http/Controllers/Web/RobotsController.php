<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Response;

final class RobotsController
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /api/',
            'Disallow: /admin/',
            'Disallow: /cms/',
            'Disallow: /storage/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8'])
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
