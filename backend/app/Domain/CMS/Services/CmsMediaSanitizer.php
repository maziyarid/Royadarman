<?php

namespace App\Domain\CMS\Services;

use App\Domain\CMS\ValueObjects\SanitizedCmsMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CmsMediaSanitizer
{
    /** @var array<string, string> */
    private const EXTENSION_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function sanitize(UploadedFile $file): SanitizedCmsMedia
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => __('ui.errors.document_incomplete')]);
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => __('ui.errors.document_unreadable')]);
        }

        $size = $file->getSize();
        $maxBytes = (int) config('royadarman.cms.media.max_bytes', 8 * 1024 * 1024);
        if ($size === false || $size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages(['file' => __('ui.errors.document_too_large')]);
        }

        $detectedMime = $this->sniff($path);
        if ($detectedMime === 'image/gif' || $detectedMime === 'image/svg+xml') {
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_type')]);
        }
        if ($detectedMime === null || ! array_key_exists($detectedMime, self::EXTENSION_BY_MIME)) {
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_type')]);
        }

        [$binary, $width, $height] = $this->reencode($path, $detectedMime);

        $extension = self::EXTENSION_BY_MIME[$detectedMime];
        $filename = strtolower((string) Str::ulid()).'.'.$extension;
        $original = Str::limit(basename($file->getClientOriginalName()), 180, '');

        return new SanitizedCmsMedia(
            binary: $binary,
            mimeType: $detectedMime,
            extension: $extension,
            filename: $filename,
            width: $width,
            height: $height,
            byteSize: strlen($binary),
            sha256: hash('sha256', $binary),
            originalFilename: $original === '' ? $filename : $original,
        );
    }

    private function sniff(string $path): ?string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $head = fread($handle, 512);
        fclose($handle);

        if (! is_string($head) || $head === '') {
            return null;
        }

        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
            return 'image/gif';
        }
        if (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        $normalized = strtolower(ltrim($head, "\xEF\xBB\xBF \t\r\n"));
        if (str_contains($normalized, '<svg') || (str_contains($normalized, '<?xml') && str_contains($normalized, 'svg'))) {
            return 'image/svg+xml';
        }

        return null;
    }

    /** @return array{0: string, 1: int, 2: int} */
    private function reencode(string $path, string $mime): array
    {
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($source === false) {
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_malformed')]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxEdge = (int) config('royadarman.cms.media.max_edge', 8000);
        $maxPixels = (int) config('royadarman.cms.media.max_pixels', 40_000_000);

        if ($width < 1 || $height < 1 || $width > $maxEdge || $height > $maxEdge || ($width * $height) > $maxPixels) {
            imagedestroy($source);
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_dimensions')]);
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($source);
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_malformed')]);
        }

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($canvas, null, 88),
            'image/png' => imagepng($canvas, null, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, null, 82) : false,
        };
        $binary = ob_get_clean();
        imagedestroy($canvas);

        if ($ok === false || ! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_malformed')]);
        }

        return [$binary, $width, $height];
    }
}
