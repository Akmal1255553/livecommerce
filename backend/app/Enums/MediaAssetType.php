<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaAssetType: string
{
    case Raw = 'raw';
    case Thumbnail = 'thumbnail';
    case HlsMaster = 'hls_master';
    case Hls720p = 'hls_720p';
    case Hls480p = 'hls_480p';
    case Hls360p = 'hls_360p';
    case Hls1080p = 'hls_1080p';
}
