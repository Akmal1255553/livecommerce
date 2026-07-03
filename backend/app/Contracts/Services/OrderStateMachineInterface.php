<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Enums\OrderActor;
use App\Enums\OrderStatus;

interface OrderStateMachineInterface
{
    public function assertCanTransition(
        OrderStatus $from,
        OrderStatus $to,
        OrderActor $actor,
    ): void;

    /**
     * @return list<OrderStatus>
     */
    public function allowedTargets(OrderStatus $from, OrderActor $actor): array;
}
