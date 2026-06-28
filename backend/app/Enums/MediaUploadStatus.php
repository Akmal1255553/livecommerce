<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaUploadStatus: string
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
