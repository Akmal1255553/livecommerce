<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Contracts\Services\CheckoutServiceInterface;
use App\Contracts\Services\CouponServiceInterface;
use App\Contracts\Services\InventoryServiceInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\PricingServiceInterface;
use App\Contracts\Services\ShippingCalculatorInterface;
use App\DTOs\Checkout\CheckoutData;
use App\DTOs\Checkout\CheckoutResult;
use App\DTOs\Order\CreateOrderData;
use App\DTOs\Order\PaymentSnapshot;
use App\DTOs\Order\ShipmentSnapshot;
use App\Enums\PaymentStatus;
use App\Events\CartCheckedOut;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Exceptions\Domain\CartStaleException;
use App\Exceptions\Domain\IdempotencyConflictException;
use App\Exceptions\PaymentFailedException;
use App\Logging\StructuredLogger;
use App\Models\Cart;
use App\Models\CheckoutIdempotencyKey;
use App\Models\Order;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService extends BaseService implements CheckoutServiceInterface
{
    public function __construct(
        StructuredLogger $logger,
        private readonly CartRepositoryInterface $carts,
        private readonly PricingServiceInterface $pricing,
        private readonly CouponServiceInterface $coupons,
        private readonly ShippingCalculatorInterface $shipping,
        private readonly OrderServiceInterface $orders,
        private readonly InventoryServiceInterface $inventory,
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {
        parent::__construct($logger);
    }

    public function checkout(CheckoutData $data): CheckoutResult
    {
        $requestHash = $this->hashRequest($data);
        $cached = $this->findIdempotencyRecord($data->userId, $data->idempotencyKey);

        if ($cached !== null) {
            if ($cached->request_hash !== $requestHash) {
                throw new IdempotencyConflictException;
            }

            $order = Order::query()
                ->with(['items.product', 'transitions', 'store', 'activeRefund'])
                ->findOrFail($cached->order_id);

            return new CheckoutResult(
                $order,
                $cached->response_json['payment_url'] ?? null,
            );
        }

        $cart = $this->carts->findOrCreateForUser($data->userId);
        $cart = $this->carts->loadWithProducts($cart);

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Cart is empty.'],
            ]);
        }

        if ($cart->version !== $data->cartVersion) {
            throw new CartStaleException;
        }

        $storeId = $this->resolveSingleStoreId($cart);
        $pricing = $this->pricing->priceCart($cart);

        /** @var array{0: Order, 1: string} $checkout */
        $checkout = DB::transaction(function () use ($data, $cart, $storeId, $pricing): array {
            $coupon = $this->coupons->resolve($data->couponCode, $pricing);
            $shipping = $this->shipping->calculate($pricing);
            $snapshots = $this->pricing->buildOrderLineSnapshots($cart);
            $totals = $this->pricing->calculateOrderTotals($snapshots, $coupon, $shipping);

            if ($totals->total->amount <= 0) {
                throw ValidationException::withMessages([
                    'cart' => ['Order total must be positive.'],
                ]);
            }

            $reservationLines = array_map(
                static fn ($snapshot) => [
                    'product_id' => $snapshot->productId,
                    'variant_id' => $snapshot->variantId,
                    'quantity' => $snapshot->quantity,
                ],
                $snapshots,
            );

            $order = $this->orders->createFromCheckout(new CreateOrderData(
                userId: $data->userId,
                storeId: $storeId,
                lines: $snapshots,
                totals: $totals,
                payment: new PaymentSnapshot(
                    provider: 'fake',
                    method: $data->paymentMethod,
                    transactionId: null,
                    amount: $totals->total,
                    status: PaymentStatus::Pending->value,
                ),
                shipment: new ShipmentSnapshot(address: $data->shippingAddress),
                notes: $data->notes,
            ));

            $reservationGroupId = $this->inventory->reserveForOrder($order->id, $reservationLines);

            $this->carts->clear($cart);
            $cart = $this->carts->incrementVersion($cart);

            CartCheckedOut::dispatch($cart, $order);

            return [$order, $reservationGroupId];
        });

        [$order, $reservationGroupId] = $checkout;

        $order = $this->orders->markAwaitingPayment($order);

        $payment = $this->paymentGateway->initiate($order);

        if (! $payment->success) {
            $this->inventory->releaseReservation($reservationGroupId);
            $this->orders->cancelForBuyer(
                $data->userId,
                $order->id,
                null,
                $payment->failureReason ?? 'Payment failed',
            );
            PaymentFailed::dispatch($order, $payment->failureReason ?? 'Payment failed');

            throw new PaymentFailedException($payment->failureReason ?? 'Payment failed');
        }

        $order = $this->orders->markPaid($order, $payment->transactionId);
        $this->inventory->confirmReservation($reservationGroupId);
        PaymentSucceeded::dispatch($order, $payment->transactionId);

        $result = new CheckoutResult($order->fresh(['items.product', 'transitions', 'store', 'activeRefund']), $payment->paymentUrl);

        $this->storeIdempotency($data, $requestHash, $result);

        return $result;
    }

    private function findIdempotencyRecord(string $userId, string $key): ?CheckoutIdempotencyKey
    {
        return CheckoutIdempotencyKey::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->where('expires_at', '>', now())
            ->first();
    }

    private function storeIdempotency(CheckoutData $data, string $requestHash, CheckoutResult $result): void
    {
        $ttlHours = (int) config('commerce.checkout_idempotency_ttl_hours', 24);

        CheckoutIdempotencyKey::query()->create([
            'user_id' => $data->userId,
            'idempotency_key' => $data->idempotencyKey,
            'request_hash' => $requestHash,
            'order_id' => $result->order->id,
            'response_json' => [
                'order_id' => $result->order->id,
                'order_number' => $result->order->order_number,
                'payment_url' => $result->paymentUrl,
            ],
            'expires_at' => now()->addHours($ttlHours),
        ]);
    }

    private function hashRequest(CheckoutData $data): string
    {
        $payload = [
            'cart_version' => $data->cartVersion,
            'shipping_address' => $data->shippingAddress,
            'payment_method' => $data->paymentMethod,
            'coupon_code' => $data->couponCode,
            'notes' => $data->notes,
        ];

        return hash('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resolveSingleStoreId(Cart $cart): string
    {
        $storeIds = $cart->items
            ->map(static fn ($item) => $item->product->store_id)
            ->unique()
            ->values();

        if ($storeIds->count() !== 1) {
            throw ValidationException::withMessages([
                'cart' => ['All items must be from the same store.'],
            ]);
        }

        return (string) $storeIds->first();
    }
}
