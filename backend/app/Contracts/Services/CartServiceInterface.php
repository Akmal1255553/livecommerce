<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Cart\CartContext;
use App\DTOs\Cart\CartViewData;
use App\Models\User;

interface CartServiceInterface
{
    public function createGuestToken(): string;

    public function getCart(CartContext $context): CartViewData;

    public function addItem(CartContext $context, string $productId, ?int $variantId, int $quantity): CartViewData;

    public function updateItem(CartContext $context, string $itemId, int $quantity): CartViewData;

    public function removeItem(CartContext $context, string $itemId): CartViewData;

    public function clear(CartContext $context): CartViewData;

    public function mergeGuestIntoUser(string $guestToken, User $user): void;
}
