<?php

declare(strict_types=1);

namespace App\DTOs\Wallet;

use App\Models\WalletTransaction;

final readonly class TopUpResult
{
    public function __construct(
        public WalletTransaction $transaction,
        public ?string $paymentUrl,
        public ?string $cryptoAddress = null,
        public ?string $cryptoAmount = null,
        public ?string $cryptoCurrency = null,
        public ?float $exchangeRate = null,
        public ?string $expiresAt = null,
        public ?string $qrPayload = null,
    ) {}
}
