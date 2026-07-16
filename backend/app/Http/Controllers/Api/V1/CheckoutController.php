<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\CheckoutServiceInterface;
use App\DTOs\Checkout\CheckoutData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutServiceInterface $checkout,
    ) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return ApiResponse::error('Idempotency-Key header is required.', 422);
        }

        $result = $this->checkout->checkout(new CheckoutData(
            userId: (string) $request->user()->id,
            cartVersion: (int) $request->validated('cart_version'),
            idempotencyKey: $idempotencyKey,
            shippingAddress: $request->validated('shipping_address'),
            paymentMethod: $request->validated('payment_method'),
            couponCode: $request->validated('coupon_code'),
            notes: $request->validated('notes'),
        ));

        return ApiResponse::success([
            'order' => (new OrderResource($result->order))->resolve($request),
            'payment_url' => $result->paymentUrl,
        ], 201);
    }
}
