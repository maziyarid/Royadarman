<?php

namespace App\Domain\Documents\Enums;

enum DocumentStatus: string
{
    case Quarantined = 'quarantined';
    case Scanning = 'scanning';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ScanFailed = 'scan_failed';
    case Deleted = 'deleted';
}

