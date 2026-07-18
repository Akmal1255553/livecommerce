<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationType: string
{
    case Direct = 'direct';
    case Order = 'order';
}
