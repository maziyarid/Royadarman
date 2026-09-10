<?php

namespace App\Domain\CMS\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    public function canBeEdited(): bool
    {
        return $this !== self::Archived;
    }
}
