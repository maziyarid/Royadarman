<?php

namespace App\Domain\CMS\Enums;

enum PostType: string
{
    case Post = 'post';
    case Page = 'page';
    case Service = 'service';
}
