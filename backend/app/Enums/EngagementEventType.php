<?php

declare(strict_types=1);

namespace App\Enums;

enum EngagementEventType: string
{
    case FeedOpen = 'feed_open';
    case VideoImpression = 'video_impression';
}
