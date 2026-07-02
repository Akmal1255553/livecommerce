<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Cart;
use App\Models\CartItem;

interface CartRepositoryInterface extends RepositoryInterface
{
    public function findOrCreateForUser(string $userId): Cart;

    public function incrementVersion(Cart $cart): Cart;

    public function findItem(Cart $cart, string $productId, ?int $variantId): ?CartItem;

    public function findItemById(Cart $cart, int $itemId): ?CartItem;

    public function addOrUpdateItem(Cart $cart, string $productId, ?int $variantId, int $quantity): CartItem;

    public function updateItemQuantity(CartItem $item, int $quantity): CartItem;

    public function deleteItem(CartItem $item): void;

    public function clear(Cart $cart): void;

    public function loadWithProducts(Cart $cart): Cart;
}
