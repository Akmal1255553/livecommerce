<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\InventoryServiceInterface;
use App\Contracts\Services\OrderServiceInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\PaymentWebhookProcessorInterface;
use App\DTOs\Payment\PaymentWebhookData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SellerPayoutStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Logging\StructuredLogger;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use App\Models\SellerPayout;
use App\Services\BaseService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaymentWebhookProcessor extends BaseService implements PaymentWebhookProcessorInterface
{
    public function __construct(
        StructuredLogger $logger,
        private readonly PaymentGatewayInterface $gateway,
        private readonly OrderServiceInterface $orders,
        private readonly InventoryServiceInterface $inventory,
    ) {
        parent::__construct($logger);
    }

    public function process(array $payload, string $rawBody, ?string $signatureHeader): void
    {
        if (! $this->gateway->verifyWebhookSignature($rawBody, $signatureHeader)) {
            throw new AccessDeniedHttpException('Invalid payment webhook signature.');
        }

        $this->apply(PaymentWebhookData::fromPayload($payload));
    }

    public function apply(PaymentWebhookData $data): void
    {
        if ($data->transactionId === '' || $data->orderId === '') {
            throw ValidationException::withMessages([
                'webhook' => ['transaction_id and order_id are required.'],
            ]);
        }

        if (! in_array($data->event, ['payment.success', 'payment.failed'], true)) {
            throw ValidationException::withMessages([
                'event' => ['Unsupported payment webhook event.'],
            ]);
        }

        if (PaymentWebhookEvent::query()->where('idempotency_key', $data->idempotencyKey())->exists()) {
            return;
        }

        try {
            DB::transaction(function () use ($data): void {
                if (PaymentWebhookEvent::query()
                    ->where('idempotency_key', $data->idempotencyKey())
                    ->lockForUpdate()
                    ->exists()) {
                    return;
                }

                match ($data->event) {
                    'payment.success' => $this->handleSuccess($data),
                    'payment.failed' => $this->handleFailure($data),
                };

                PaymentWebhookEvent::query()->create([
                    'idempotency_key' => $data->idempotencyKey(),
                    'event' => $data->event,
                    'transaction_id' => $data->transactionId,
                    'order_id' => $data->orderId,
                    'payload' => [
                        'event' => $data->event,
                        'transaction_id' => $data->transactionId,
                        'order_id' => $data->orderId,
                        'amount' => $data->amount,
                        'currency' => $data->currency,
                    ],
                    'processed_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return;
            }

            throw $e;
        }
    }

    private function handleSuccess(PaymentWebhookData $data): void
    {
        $order = Order::query()->whereKey($data->orderId)->lockForUpdate()->first();

        if ($order === null) {
            throw ValidationException::withMessages([
                'order_id' => ['Order not found.'],
            ]);
        }

        if ($order->status === OrderStatus::Paid) {
            return;
        }

        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw ValidationException::withMessages([
                'order' => ['Order is not awaiting payment.'],
            ]);
        }

        if ((int) $order->total !== $data->amount) {
            throw ValidationException::withMessages([
                'amount' => ['Webhook amount does not match order total.'],
            ]);
        }

        if ($data->currency !== '' && strtoupper($data->currency) !== strtoupper((string) $order->currency)) {
            throw ValidationException::withMessages([
                'currency' => ['Webhook currency does not match order.'],
            ]);
        }

        $expectedTxn = $order->payment_transaction_id ?? $order->payment_reference;
        if ($expectedTxn !== null && $expectedTxn !== '' && ! hash_equals((string) $expectedTxn, $data->transactionId)) {
            // Provider adapters store "{driver}-pending-{orderId}" until the real gateway txn arrives.
            if (! str_contains((string) $expectedTxn, '-pending-')) {
                throw ValidationException::withMessages([
                    'transaction_id' => ['Webhook transaction does not match order payment reference.'],
                ]);
            }
        }

        $paid = $this->orders->markPaid($order, $data->transactionId);
        $this->inventory->confirmReservationForOrder($paid->id);

        SellerPayout::query()->firstOrCreate(
            ['order_id' => $paid->id],
            [
                'store_id' => $paid->store_id,
                'amount' => (int) $paid->total,
                'currency' => $paid->currency ?? 'UZS',
                'status' => SellerPayoutStatus::Pending->value,
            ],
        );

        PaymentSucceeded::dispatch($paid, $data->transactionId);
    }

    private function handleFailure(PaymentWebhookData $data): void
    {
        $order = Order::query()->whereKey($data->orderId)->lockForUpdate()->first();

        if ($order === null) {
            throw ValidationException::withMessages([
                'order_id' => ['Order not found.'],
            ]);
        }

        if ($order->status === OrderStatus::Cancelled) {
            return;
        }

        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw ValidationException::withMessages([
                'order' => ['Order is not awaiting payment.'],
            ]);
        }

        $expectedTxn = $order->payment_transaction_id ?? $order->payment_reference;
        if ($expectedTxn !== null && $expectedTxn !== '' && ! hash_equals((string) $expectedTxn, $data->transactionId)) {
            // Provider adapters store "{driver}-pending-{orderId}" until the real gateway txn arrives.
            if (! str_contains((string) $expectedTxn, '-pending-')) {
                throw ValidationException::withMessages([
                    'transaction_id' => ['Webhook transaction does not match order payment reference.'],
                ]);
            }
        }

        $cancelled = $this->orders->cancelForBuyer(
            $order->user_id,
            $order->id,
            'webhook-fail-'.$data->transactionId,
            'Payment failed',
        );
        $cancelled->payment_status = PaymentStatus::Failed;
        $cancelled->save();

        PaymentFailed::dispatch($cancelled, 'Payment failed');
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';

        return $sqlState === '23000' || str_contains($e->getMessage(), 'UNIQUE');
    }
}
