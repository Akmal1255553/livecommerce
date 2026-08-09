<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

use App\Models\Order;
use App\Models\WalletTransaction;

/**
 * What a gateway needs in order to charge, independent of what is being paid for.
 *
 * `reference` is the merchant transaction id handed to the provider and echoed back
 * on the webhook, so it has to identify its subject on its own. Orders keep their
 * bare UUID — references already in flight at providers must stay resolvable — while
 * wallet top-ups are namespaced with a prefix.
 */
final readonly class PaymentIntent
{
    public const WALLET_TOPUP_PREFIX = 'wt-';

    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public string $description,
        public string $sandboxUrl,
    ) {}

    public static function forOrder(Order $order): self
    {
        return new self(
            reference: (string) $order->id,
            amount: (int) $order->total,
            currency: strtoupper((string) ($order->currency ?? 'UZS')),
            description: 'Order '.($order->order_number ?? $order->id),
            sandboxUrl: url('/api/v1/payments/sandbox/'.$order->id),
        );
    }

    public static function forWalletTopUp(WalletTransaction $transaction): self
    {
        return new self(
            reference: self::WALLET_TOPUP_PREFIX.$transaction->id,
            amount: (int) $transaction->amount,
            currency: strtoupper($transaction->currency),
            description: 'Wallet top-up',
            sandboxUrl: url('/api/v1/wallet/topups/'.$transaction->id.'/confirm'),
        );
    }

    public static function isWalletTopUpReference(string $reference): bool
    {
        return str_starts_with($reference, self::WALLET_TOPUP_PREFIX);
    }

    public static function walletTransactionIdFrom(string $reference): string
    {
        return substr($reference, strlen(self::WALLET_TOPUP_PREFIX));
    }

    /** Sandbox stands in for the provider page while merchant credentials are missing. */
    public function sandboxUrlFor(string $transactionId): string
    {
        return $this->sandboxUrl.'?txn='.urlencode($transactionId);
    }
}
