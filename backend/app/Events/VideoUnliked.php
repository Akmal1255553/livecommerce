<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VideoUnliked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $videoId,
        public readonly string $userId,
    ) {}
}
