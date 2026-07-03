<?php

declare(strict_types=1);

use App\Contracts\Services\OrderNumberGeneratorInterface;
use App\Enums\OrderActor;
use App\Enums\OrderStatus;
use App\Exceptions\Domain\InvalidOrderTransitionException;
use App\Services\Order\DateSequenceOrderNumberGenerator;
use App\Services\Order\OrderStateMachine;

test('date sequence order number matches format', function () {
    $generator = app(OrderNumberGeneratorInterface::class);

    expect($generator->generate())->toMatch('/^LC-\d{8}-\d{6}$/');
});

test('order numbers are unique', function () {
    $generator = new DateSequenceOrderNumberGenerator;

    $first = $generator->generate();
    $second = $generator->generate();

    expect($first)->not->toBe($second);
});

test('state machine allows seller fulfillment path', function () {
    $machine = new OrderStateMachine;

    $machine->assertCanTransition(OrderStatus::Paid, OrderStatus::Packing, OrderActor::Seller);
    $machine->assertCanTransition(OrderStatus::Packing, OrderStatus::ReadyToShip, OrderActor::Seller);
    $machine->assertCanTransition(OrderStatus::ReadyToShip, OrderStatus::Shipped, OrderActor::Seller);

    expect(true)->toBeTrue();
});

test('buyer cannot transition to packing', function () {
    $machine = new OrderStateMachine;

    expect(fn () => $machine->assertCanTransition(OrderStatus::Paid, OrderStatus::Packing, OrderActor::Buyer))
        ->toThrow(InvalidOrderTransitionException::class);
});

test('paid cannot be cancelled', function () {
    $machine = new OrderStateMachine;

    expect(fn () => $machine->assertCanTransition(OrderStatus::Paid, OrderStatus::Cancelled, OrderActor::Buyer))
        ->toThrow(InvalidOrderTransitionException::class);
});

test('refund branch from paid allowed for buyer', function () {
    $machine = new OrderStateMachine;

    $machine->assertCanTransition(OrderStatus::Paid, OrderStatus::RefundRequested, OrderActor::Buyer);

    expect(true)->toBeTrue();
});

test('invalid transition throws', function () {
    $machine = new OrderStateMachine;

    expect(fn () => $machine->assertCanTransition(OrderStatus::Pending, OrderStatus::Shipped, OrderActor::Seller))
        ->toThrow(InvalidOrderTransitionException::class);
});

test('allowed targets returns correct set for seller on paid', function () {
    $machine = new OrderStateMachine;

    expect($machine->allowedTargets(OrderStatus::Paid, OrderActor::Seller))
        ->toBe([OrderStatus::Packing]);
});
