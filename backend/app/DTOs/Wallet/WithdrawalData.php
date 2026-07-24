<?php

declare(strict_types=1);

namespace App\DTOs\Wallet;

final readonly class WithdrawalData
{
    public function __construct(
        public int $amount,
        public string $method,
        public string $cardNumber,
        public string $cardHolder,
    ) {}

    public function cardLast4(): string
    {
        $digits = preg_replace('/\D/', '', $this->cardNumber) ?? '';

        return substr($digits, -4);
    }
}
