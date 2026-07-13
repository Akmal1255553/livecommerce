<?php

declare(strict_types=1);

namespace App\Enums;

enum LiveChatMessageType: string
{
    case User = 'user';
    case System = 'system';
    case Commerce = 'commerce';
}
