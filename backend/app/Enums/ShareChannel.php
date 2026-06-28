<?php

declare(strict_types=1);

namespace App\Enums;

enum ShareChannel: string
{
    case Link = 'link';
    case Copy = 'copy';
    case External = 'external';
}
