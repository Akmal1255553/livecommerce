<?php

declare(strict_types=1);

namespace App\DTOs\Live;

use App\DTOs\Cart\CartViewData;
use App\Models\LiveChatMessage;

final readonly class LiveAddToCartResult
{
    public function __construct(
        public CartViewData $cart,
        public LiveChatMessage $message,
    ) {}
}
