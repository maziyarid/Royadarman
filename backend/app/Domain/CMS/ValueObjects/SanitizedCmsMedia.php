<?php

namespace App\Domain\CMS\ValueObjects;

final readonly class SanitizedCmsMedia
{
    public function __construct(
        public string $binary,
        public string $mimeType,
        public string $extension,
        public string $filename,
        public int $width,
        public int $height,
        public int $byteSize,
        public string $sha256,
        public string $originalFilename,
    ) {}
}
