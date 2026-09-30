<?php

namespace App\Domain\Discovery\Enums;

enum SuitabilityStatus: string
{
    case Suitable = 'suitable';
    case NotSuitable = 'not_suitable';
    case Unknown = 'unknown';
}
