<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Contracts\Services\CartServiceInterface;
use App\Contracts\Services\InventoryServiceInterface;
use App\Contracts\Services\PricingServiceInterface;
use App\DTOs\Cart\CartContext;
use App\DTOs\Cart\CartViewData;
use App\DTOs\Cart\GuestCartData;
use App\Events\CartExpired;
use App\Events\CartItemAdded;
use App\Events\CartItemRemoved;
use App\Events\CartMerged;
use App\Events\CartUpdated;
use App\Exceptions\Domain\ResourceNotFoundException;
use App\Exceptions\Domain\UnauthorizedException;
use App\Logging\StructuredLogger;
use App\Models\Cart;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService extends BaseService implements CartServiceInterface
{
    public function __construct(
        StructuredLogger $logger,
        private readonly CartRepositoryInterface $carts,
        private readonly GuestCartStore $guestCarts,
        private readonly InventoryServiceInterface $inventory,
        private readonly PricingServiceInterface $pricing,
    ) {
        parent::__construct($logger);
    }

    public function createGuestToken(): string
    {
        $token = $this->guestCarts->createToken();
        $this->guestCarts->put($token, GuestCartData::empty());

        return $token;
    }

    public function getCart(CartContext $context): CartViewData
    {
        return $this->buildView($context);
    }

    public function addItem(CartContext $context, string $productId, ?int $variantId, int $quantity): CartViewData
    {
        $this->assertPositiveQuantity($quantity);
        $this->inventory->assertAvailable($productId, $variantId, $quantity);

        if ($context->isUser()) {
            return DB::transaction(function () use ($context, $productId, $variantId, $quantity): CartViewData {
                $cart = $this->carts->findOrCreateForUser((string) $context->userId);
                $existing = $this->carts->findItem($cart, $productId, $variantId);
                $newQuantity = ($existing !== null ? $existing->quantity : 0) + $quantity;
                $this->assertMaxQuantity($newQuantity);

                $this->inventory->assertAvailable($productId, $variantId, $newQuantity);
                $this->carts->addOrUpdateItem($cart, $productId, $variantId, $newQuantity);
                $cart = $this->carts->incrementVersion($cart);

                CartItemAdded::dispatch(
                    (string) $cart->id,
                    $context->userId,
                    null,
                    $productId,
                    $quantity,
                );
                $this->dispatchCartUpdated($cart, $context);

                return $this->buildUserView($cart);
            });
        }

        return $this->mutateGuest($context, function (GuestCartData $guest) use ($productId, $variantId, $quantity, $context): GuestCartData {
            $items = $guest->items;
            $found = false;
            $newQuantity = $quantity;

            foreach ($items as &$item) {
                if ($item['product_id'] === $productId && ($item['variant_id'] ?? null) === $variantId) {
                    $item['quantity'] += $quantity;
                    $found = true;
                    $newQuantity = $item['quantity'];
                    break;
                }
            }
            unset($item);

            if (! $found) {
                $items[] = [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                ];
                $newQuantity = $quantity;
            }

            $this->assertMaxQuantity($newQuantity);
            $this->inventory->assertAvailable($productId, $variantId, $newQuantity);

            CartItemAdded::dispatch(
                $context->guestToken ?? '',
                null,
                $context->guestToken,
                $productId,
                $quantity,
            );

            return new GuestCartData($guest->version + 1, $items);
        }, $context);
    }

    public function updateItem(CartContext $context, string $itemId, int $quantity): CartViewData
    {
        if ($quantity <= 0) {
            return $this->removeItem($context, $itemId);
        }

        $this->assertMaxQuantity($quantity);

        if ($context->isUser()) {
            return DB::transaction(function () use ($context, $itemId, $quantity): CartViewData {
                $cart = $this->carts->findOrCreateForUser((string) $context->userId);
                $item = $this->carts->findItemById($cart, (int) $itemId);

                if ($item === null) {
                    throw new ResourceNotFoundException('Cart item not found.');
                }

                $this->inventory->assertAvailable($item->product_id, $item->variant_id, $quantity);
                $this->carts->updateItemQuantity($item, $quantity);
                $cart = $this->carts->incrementVersion($cart);
                $this->dispatchCartUpdated($cart, $context);

                return $this->buildUserView($cart);
            });
        }

        return $this->mutateGuest($context, function (GuestCartData $guest) use ($itemId, $quantity): GuestCartData {
            $items = $guest->items;
            $updated = false;

            foreach ($items as &$item) {
                if ($this->guestItemId($item['product_id'], $item['variant_id'] ?? null) === $itemId) {
                    $this->inventory->assertAvailable(
                        $item['product_id'],
                        $item['variant_id'] ?? null,
                        $quantity,
                    );
                    $item['quantity'] = $quantity;
                    $updated = true;
                    break;
                }
            }
            unset($item);

            if (! $updated) {
                throw new ResourceNotFoundException('Cart item not found.');
            }

            return new GuestCartData($guest->version + 1, $items);
        }, $context);
    }

    public function removeItem(CartContext $context, string $itemId): CartViewData
    {
        if ($context->isUser()) {
            return DB::transaction(function () use ($context, $itemId): CartViewData {
                $cart = $this->carts->findOrCreateForUser((string) $context->userId);
                $item = $this->carts->findItemById($cart, (int) $itemId);

                if ($item === null) {
                    throw new ResourceNotFoundException('Cart item not found.');
                }

                $this->carts->deleteItem($item);
                $cart = $this->carts->incrementVersion($cart);

                CartItemRemoved::dispatch((string) $cart->id, $item->id);
                $this->dispatchCartUpdated($cart, $context);

                return $this->buildUserView($cart);
            });
        }

        return $this->mutateGuest($context, function (GuestCartData $guest) use ($itemId): GuestCartData {
            $items = array_values(array_filter(
                $guest->items,
                fn (array $item): bool => $this->guestItemId($item['product_id'], $item['variant_id'] ?? null) !== $itemId,
            ));

            if (count($items) === count($guest->items)) {
                throw new ResourceNotFoundException('Cart item not found.');
            }

            CartItemRemoved::dispatch($itemId, $itemId);

            return new GuestCartData($guest->version + 1, $items);
        }, $context);
    }

    public function clear(CartContext $context): CartViewData
    {
        if ($context->isUser()) {
            return DB::transaction(function () use ($context): CartViewData {
                $cart = $this->carts->findOrCreateForUser((string) $context->userId);
                $this->carts->clear($cart);
                $cart = $this->carts->incrementVersion($cart);
                $this->dispatchCartUpdated($cart, $context);

                return $this->buildUserView($cart);
            });
        }

        return $this->mutateGuest($context, fn (): GuestCartData => new GuestCartData(
            ($this->loadGuestOrFail($context)->version) + 1,
            [],
        ), $context);
    }

    public function mergeGuestIntoUser(string $guestToken, User $user): void
    {
        $guest = $this->guestCarts->get($guestToken);

        if ($guest === null || $guest->items === []) {
            return;
        }

        DB::transaction(function () use ($guestToken, $user, $guest): void {
            $cart = $this->carts->findOrCreateForUser($user->id);

            foreach ($guest->items as $item) {
                $existing = $this->carts->findItem(
                    $cart,
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                );
                $quantity = min(
                    ($existing !== null ? $existing->quantity : 0) + $item['quantity'],
                    (int) config('commerce.cart_max_quantity_per_line', 99),
                );

                $this->inventory->assertAvailable(
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                    $quantity,
                );

                $this->carts->addOrUpdateItem(
                    $cart,
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                    $quantity,
                );
            }

            $cart = $this->carts->incrementVersion($cart);
            $this->guestCarts->delete($guestToken);

            CartMerged::dispatch($user->id, $guestToken, count($guest->items));
            CartUpdated::dispatch(
                (string) $cart->id,
                $user->id,
                null,
                $cart->items()->count(),
                $cart->version,
            );
        });
    }

    private function buildView(CartContext $context): CartViewData
    {
        if ($context->isUser()) {
            $cart = $this->carts->findOrCreateForUser((string) $context->userId);

            return $this->buildUserView($cart);
        }

        $token = $context->guestToken;

        if ($token === null) {
            throw new UnauthorizedException('Guest cart token is required.');
        }

        $guest = $this->guestCarts->get($token);

        if ($guest === null) {
            CartExpired::dispatch($token);

            return new CartViewData(
                id: $token,
                type: 'guest',
                version: 0,
                lines: [],
                pricing: $this->pricing->priceLines([]),
            );
        }

        return $this->buildGuestView($token, $guest);
    }

    private function buildUserView(Cart $cart): CartViewData
    {
        $cart = $this->carts->loadWithProducts($cart);
        $pricing = $this->pricing->priceCart($cart);

        return new CartViewData(
            id: (string) $cart->id,
            type: 'user',
            version: $cart->version,
            lines: $pricing->lines,
            pricing: $pricing,
        );
    }

    private function buildGuestView(string $token, GuestCartData $guest): CartViewData
    {
        $lines = array_map(fn (array $item): array => [
            'item_id' => $this->guestItemId($item['product_id'], $item['variant_id'] ?? null),
            'product_id' => $item['product_id'],
            'variant_id' => $item['variant_id'] ?? null,
            'quantity' => $item['quantity'],
        ], $guest->items);

        $pricing = $this->pricing->priceLines($lines);

        return new CartViewData(
            id: $token,
            type: 'guest',
            version: $guest->version,
            lines: $pricing->lines,
            pricing: $pricing,
        );
    }

    /**
     * @param  callable(GuestCartData): GuestCartData  $mutator
     */
    private function mutateGuest(
        CartContext $context,
        callable $mutator,
        CartContext $eventContext,
    ): CartViewData {
        $token = $context->guestToken;

        if ($token === null) {
            throw new UnauthorizedException('Guest cart token is required.');
        }

        $guest = $this->loadGuestOrFail($context);
        $updated = $mutator($guest);
        $this->guestCarts->put($token, $updated);

        CartUpdated::dispatch(
            $token,
            null,
            $token,
            count($updated->items),
            $updated->version,
        );

        return $this->buildGuestView($token, $updated);
    }

    private function loadGuestOrFail(CartContext $context): GuestCartData
    {
        $token = $context->guestToken;

        if ($token === null) {
            throw new UnauthorizedException('Guest cart token is required.');
        }

        $guest = $this->guestCarts->get($token);

        if ($guest === null) {
            CartExpired::dispatch($token);
            throw new ResourceNotFoundException('Guest cart not found or expired.');
        }

        return $guest;
    }

    private function guestItemId(string $productId, ?int $variantId): string
    {
        return $productId.':'.($variantId ?? 'null');
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }
    }

    private function assertMaxQuantity(int $quantity): void
    {
        $max = (int) config('commerce.cart_max_quantity_per_line', 99);

        if ($quantity > $max) {
            throw ValidationException::withMessages([
                'quantity' => ["Quantity cannot exceed {$max}."],
            ]);
        }
    }

    private function dispatchCartUpdated(Cart $cart, CartContext $context): void
    {
        $cart = $this->carts->loadWithProducts($cart);

        CartUpdated::dispatch(
            (string) $cart->id,
            $context->userId,
            $context->guestToken,
            $cart->items->count(),
            $cart->version,
        );
    }
}
