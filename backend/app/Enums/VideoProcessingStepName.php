<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoProcessingStepName: string
{
    case VirusScan = 'virus_scan';
    case Metadata = 'metadata';
    case Thumbnail = 'thumbnail';
    case Transcode = 'transcode';
    case Moderation = 'moderation';
    case Publish = 'publish';
}
