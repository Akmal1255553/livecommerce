<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductAttachedToVideo implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $productIds
     */
    public function __construct(
        public readonly string $videoId,
        public readonly string $userId,
        public readonly array $productIds,
        public readonly ?string $featuredProductId,
    ) {}
}
