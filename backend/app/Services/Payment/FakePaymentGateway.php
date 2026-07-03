<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Services\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentInitiationResult;
use App\Models\Order;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public function initiate(Order $order): PaymentInitiationResult
    {
        return PaymentInitiationResult::succeeded(
            transactionId: 'fake-'.$order->id,
        );
    }
}
