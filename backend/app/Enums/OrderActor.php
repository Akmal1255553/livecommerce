<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderActor: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Admin = 'admin';
    case System = 'system';
    case PaymentGateway = 'payment_gateway';
}
