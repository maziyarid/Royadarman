<?php

namespace App\Domain\Documents\ValueObjects;

final readonly class ScanResult
{
    public function __construct(
        public bool $clean,
        public string $engine,
        public string $reference,
    ) {}
}

