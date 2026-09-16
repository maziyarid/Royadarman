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

    public function maxKilobytes(): int
    {
        $bytes = (int) config('royadarman.cms.media.max_bytes', 8 * 1024 * 1024);

        return max(1, (int) ceil($bytes / 1024));
    }

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

        $this->assertSafeRasterHeader($path);

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

    private function assertSafeRasterHeader(string $path): void
    {
        $info = @getimagesize($path);
        if ($info === false || ! isset($info[0], $info[1])) {
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_malformed')]);
        }

        $this->assertWithinLimits((int) $info[0], (int) $info[1]);
    }

    private function assertWithinLimits(int $width, int $height): void
    {
        $maxEdge = (int) config('royadarman.cms.media.max_edge', 8000);
        $maxPixels = (int) config('royadarman.cms.media.max_pixels', 40_000_000);

        if ($width < 1 || $height < 1 || $width > $maxEdge || $height > $maxEdge || ($width * $height) > $maxPixels) {
            throw ValidationException::withMessages(['file' => __('ui.errors.cms_media_dimensions')]);
        }
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

        $source = $this->orientJpeg($source, $path, $mime);

        $width = imagesx($source);
        $height = imagesy($source);

        try {
            $this->assertWithinLimits($width, $height);
        } catch (ValidationException $exception) {
            imagedestroy($source);
            throw $exception;
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

    private function orientJpeg(\GdImage $image, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        if (! is_array($exif) || ! isset($exif['Orientation'])) {
            return $image;
        }

        return match ((int) $exif['Orientation']) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->flip($this->rotate($image, -90), IMG_FLIP_HORIZONTAL),
            6 => $this->rotate($image, -90),
            7 => $this->flip($this->rotate($image, -90), IMG_FLIP_HORIZONTAL),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    private function rotate(\GdImage $image, float $angle): \GdImage
    {
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        if ($rotated !== $image) {
            imagedestroy($image);
        }

        return $rotated;
    }
}
