<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $commentId,
        public readonly string $videoId,
        public readonly string $userId,
        public readonly string $ownerId,
    ) {}
}
