<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\VideoLiked;

class NotifyOnVideoLiked
{
    public function handle(VideoLiked $event): void
    {
        // Stub — NEW_LIKE notification in a future sprint.
    }
}
