<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoProcessingStepName: string
{
    case VirusScan = 'virus_scan';
    case Metadata = 'metadata';
    case Validate = 'validate';
    case Thumbnail = 'thumbnail';
    case TranscodeHls720 = 'transcode_hls_720';
    case TranscodeHls480 = 'transcode_hls_480';
    case Moderation = 'moderation';
    case Publish = 'publish';
}
