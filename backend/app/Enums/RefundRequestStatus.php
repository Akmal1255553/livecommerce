<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
