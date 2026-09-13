<?php

namespace App\Domain\CMS\Enums;

enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Spam = 'spam';
    case Trash = 'trash';
}
