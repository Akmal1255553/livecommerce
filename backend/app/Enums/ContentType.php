<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentType: string
{
    case Video = 'video';
    case Live = 'live';
}
