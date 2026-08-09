<?php

declare(strict_types=1);

namespace App\DTOs\Payment;

/** What a provider reference points at, reduced to what a webhook has to verify. */
final readonly class PaymentSubject
{
    /**
     * @param  bool  $chargeable  False once the subject has been paid, cancelled, or otherwise
     *                            left the state where accepting money is still correct.
     */
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public bool $chargeable = true,
    ) {}
}
