<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoStatus: string
{
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Processing = 'processing';
    case Published = 'published';
    case Rejected = 'rejected';
    case Hidden = 'hidden';
    case Failed = 'failed';
}
