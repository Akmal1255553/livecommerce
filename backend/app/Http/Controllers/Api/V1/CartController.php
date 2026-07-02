<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\CartServiceInterface;
use App\DTOs\Cart\CartContext;
use App\Exceptions\Domain\UnauthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartServiceInterface $cartService,
    ) {}

    public function createGuest(): JsonResponse
    {
        $token = $this->cartService->createGuestToken();

        return ApiResponse::created([
            'guest_cart_token' => $token,
            'type' => 'guest',
            'version' => 1,
            'items' => [],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request, requireActor: false);

        if ($context === null) {
            return ApiResponse::success([
                'id' => null,
                'type' => 'anonymous',
                'version' => 0,
                'items' => [],
                'summary' => [
                    'subtotal' => ['amount' => 0, 'currency' => 'UZS'],
                    'discount_total' => ['amount' => 0, 'currency' => 'UZS'],
                    'shipping_estimate' => ['amount' => 0, 'currency' => 'UZS'],
                    'currency' => 'UZS',
                    'item_count' => 0,
                ],
            ]);
        }

        $cart = $this->cartService->getCart($context);

        return ApiResponse::success(new CartResource($cart));
    }

    public function storeItem(AddCartItemRequest $request): JsonResponse
    {
        $context = $this->resolveContext($request, requireActor: true);
        $cart = $this->cartService->addItem(
            $context,
            $request->validated('product_id'),
            $request->validated('variant_id'),
            (int) $request->validated('quantity', 1),
        );

        return ApiResponse::success(new CartResource($cart));
    }

    public function updateItem(UpdateCartItemRequest $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request, requireActor: true);
        $cart = $this->cartService->updateItem(
            $context,
            $id,
            (int) $request->validated('quantity'),
        );

        return ApiResponse::success(new CartResource($cart));
    }

    public function destroyItem(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request, requireActor: true);
        $cart = $this->cartService->removeItem($context, $id);

        return ApiResponse::success(new CartResource($cart));
    }

    public function clear(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request, requireActor: true);
        $cart = $this->cartService->clear($context);

        return ApiResponse::success(new CartResource($cart));
    }

    private function resolveContext(Request $request, bool $requireActor): ?CartContext
    {
        if ($user = $request->user()) {
            return CartContext::forUser($user->id);
        }

        $guestToken = $request->header('X-Guest-Cart-Token');

        if (is_string($guestToken) && $guestToken !== '') {
            return CartContext::forGuest($guestToken);
        }

        if ($requireActor) {
            throw new UnauthorizedException('Authentication or guest cart token is required.');
        }

        return null;
    }
}
