<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CommentCreated;

class NotifyOnComment
{
    public function handle(CommentCreated $event): void
    {
        // Stub — NEW_COMMENT notification in a future sprint.
    }
}
