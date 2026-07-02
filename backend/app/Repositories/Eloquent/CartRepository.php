<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Models\Cart;
use App\Models\CartItem;

/**
 * @extends BaseEloquentRepository<Cart>
 */
class CartRepository extends BaseEloquentRepository implements CartRepositoryInterface
{
    public function __construct(Cart $model)
    {
        parent::__construct($model);
    }

    public function findOrCreateForUser(string $userId): Cart
    {
        /** @var Cart */
        return $this->model->newQuery()->firstOrCreate(
            ['user_id' => $userId],
            ['version' => 1],
        );
    }

    public function incrementVersion(Cart $cart): Cart
    {
        $cart->version = $cart->version + 1;
        $cart->save();

        return $cart->fresh() ?? $cart;
    }

    public function findItem(Cart $cart, string $productId, ?int $variantId): ?CartItem
    {
        $query = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $productId);

        if ($variantId === null) {
            $query->whereNull('variant_id');
        } else {
            $query->where('variant_id', $variantId);
        }

        return $query->first();
    }

    public function findItemById(Cart $cart, int $itemId): ?CartItem
    {
        return CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('id', $itemId)
            ->first();
    }

    public function addOrUpdateItem(Cart $cart, string $productId, ?int $variantId, int $quantity): CartItem
    {
        $item = $this->findItem($cart, $productId, $variantId);

        if ($item === null) {
            /** @var CartItem */
            return CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
        }

        $item->quantity = $quantity;
        $item->save();

        return $item;
    }

    public function updateItemQuantity(CartItem $item, int $quantity): CartItem
    {
        $item->quantity = $quantity;
        $item->save();

        return $item;
    }

    public function deleteItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        CartItem::query()->where('cart_id', $cart->id)->delete();
    }

    public function loadWithProducts(Cart $cart): Cart
    {
        return $cart->load([
            'items.product.images',
            'items.product.store',
            'items.variant',
        ]);
    }
}
