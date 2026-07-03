<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Payment\PaymentInitiationResult;
use App\Models\Order;

interface PaymentGatewayInterface
{
    public function initiate(Order $order): PaymentInitiationResult;
}
