<?php

namespace App\Domain\CMS\Enums;

enum MenuItemType: string
{
    case Custom = 'custom';
    case Post = 'post';
    case Page = 'page';
    case Category = 'category';
    case Url = 'url';
}
