<?php

namespace App\Domain\Discovery;

final class OpenMapConfig
{
    public static function tileUrl(): string
    {
        return (string) config(
            'royadarman.maps.tile_url',
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        );
    }

    public static function attribution(): string
    {
        return (string) config(
            'royadarman.maps.attribution',
            '© OpenStreetMap contributors',
        );
    }

    public static function attributionUrl(): string
    {
        return (string) config(
            'royadarman.maps.attribution_url',
            'https://www.openstreetmap.org/copyright',
        );
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
