<?php

declare(strict_types=1);

namespace App\Services\Recommendation\DTOs;

use App\DTOs\DataTransferObject;
use App\Enums\FeedStrategy;
use App\Models\User;

readonly class FeedContext extends DataTransferObject
{
    public function __construct(
        public FeedStrategy $strategy,
        public ?User $user = null,
        public int $limit = 20,
        public ?string $cursor = null,
    ) {}
}
