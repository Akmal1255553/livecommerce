<?php

declare(strict_types=1);

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Exceptions\Domain\OrderItemImmutableException;

test('order item rejects quantity update', function () {
    $data = createPaidOrderForBuyer();
    $item = $data['order']->items->first();

    expect(fn () => $item->update(['quantity' => 99]))
        ->toThrow(OrderItemImmutableException::class);
});

test('order item rejects unit price update', function () {
    $data = createPaidOrderForBuyer();
    $item = $data['order']->items->first();

    expect(fn () => $item->update(['unit_price' => 1]))
        ->toThrow(OrderItemImmutableException::class);
});

test('order item rejects product title and sku update', function () {
    $data = createPaidOrderForBuyer();
    $item = $data['order']->items->first();

    expect(fn () => $item->update(['product_title' => 'Hacked']))
        ->toThrow(OrderItemImmutableException::class);
    expect(fn () => $item->update(['sku' => 'HACK']))
        ->toThrow(OrderItemImmutableException::class);
});

test('repository has no updateOrderItem method', function () {
    expect(method_exists(app(OrderRepositoryInterface::class), 'updateOrderItem'))->toBeFalse();
});

test('order totals match sum of immutable line snapshots', function () {
    $data = createPaidOrderForBuyer();
    $order = $data['order']->fresh('items');

    $sum = $order->items->sum('line_total');

    expect($order->subtotal)->toBe((int) $sum);
});
