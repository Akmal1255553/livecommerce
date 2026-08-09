<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\WalletServiceInterface;
use App\DTOs\Payment\PaymentIntent;
use App\DTOs\Payment\PaymentSubject;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Resolves a merchant reference to the amount a provider is allowed to charge,
 * so webhook controllers can validate a callback without knowing whether it
 * belongs to an order or a wallet top-up.
 */
final class PaymentSubjectLookup
{
    public function __construct(
        private readonly WalletServiceInterface $wallet,
    ) {}

    public function find(string $reference): ?PaymentSubject
    {
        if (PaymentIntent::isWalletTopUpReference($reference)) {
            $topUp = $this->wallet->pendingTopUpFor($reference);

            if ($topUp === null) {
                return null;
            }

            return new PaymentSubject(
                reference: $reference,
                amount: $topUp->amount,
                currency: strtoupper($topUp->currency),
            );
        }

        if (! Str::isUuid($reference)) {
            return null;
        }

        $order = Order::query()->find($reference);

        if ($order === null) {
            return null;
        }

        return new PaymentSubject(
            reference: $reference,
            amount: (int) $order->total,
            currency: strtoupper((string) ($order->currency ?? 'UZS')),
            chargeable: $order->status === OrderStatus::AwaitingPayment,
        );
    }
}
