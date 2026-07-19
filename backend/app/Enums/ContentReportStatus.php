<?php

declare(strict_types=1);

namespace App\Enums;

enum ContentReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
