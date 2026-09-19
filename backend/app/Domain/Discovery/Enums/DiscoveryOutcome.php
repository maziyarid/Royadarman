<?php

namespace App\Domain\Discovery\Enums;

enum DiscoveryOutcome: string
{
    case Match = 'match';
    case InsufficientData = 'insufficient_data';
    case NotSuitable = 'not_suitable';
    case OutsideRadius = 'outside_radius';
}
