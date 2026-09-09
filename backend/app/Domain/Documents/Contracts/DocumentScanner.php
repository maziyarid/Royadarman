<?php

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\ValueObjects\ScanResult;

interface DocumentScanner
{
    public function scan(string $absolutePath): ScanResult;
}

