<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentReportTarget: string
{
    case User = 'user';
    case Video = 'video';
    case Product = 'product';
    case Store = 'store';
}
