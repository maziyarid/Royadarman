<?php

namespace App\Domain\Discovery;

final class NeshanMapConfig
{
    public static function apiKey(): ?string
    {
        $key = trim((string) config('royadarman.neshan.map_api_key', ''));

        return $key === '' ? null : $key;
    }

    public static function enabled(): bool
    {
        return self::apiKey() !== null;
    }

    public static function viteEntryBuilt(): bool
    {
        $manifestPath = public_path('build/manifest.json');
        if (! is_file($manifestPath)) {
            return false;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($manifest) && isset($manifest['resources/js/discovery-map.js']);
    }
}
