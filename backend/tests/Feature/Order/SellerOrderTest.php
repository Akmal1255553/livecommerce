<?php

declare(strict_types=1);

use App\Contracts\Services\OrderServiceInterface;
use App\Enums\OrderStatus;
use App\Events\OrderPackingStarted;
use App\Events\OrderShipped;
use Illuminate\Support\Facades\Event;

test('seller lists store orders filtered by status', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['seller']['token'])
        ->getJson('/api/v1/seller/orders?status=paid')
        ->assertOk()
        ->assertJsonPath('data.0.id', $data['order']->id);
});

test('seller transitions paid to packing to ready_to_ship to shipped', function () {
    $data = createPaidOrderForBuyer();

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/orders/'.$data['order']->id.'/status', ['status' => 'packing'])
        ->assertOk()
        ->assertJsonPath('data.status', 'packing');

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/orders/'.$data['order']->id.'/status', ['status' => 'ready_to_ship'])
        ->assertOk();

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/orders/'.$data['order']->id.'/status', [
            'status' => 'shipped',
            'tracking_number' => 'UZ123456789',
            'carrier' => 'UzPost',
        ])
        ->assertOk()
        ->assertJsonPath('data.shipment.tracking_number', 'UZ123456789');
});

test('shipped requires tracking number', function () {
    $data = createPaidOrderForBuyer();
    $orderService = app(OrderServiceInterface::class);
    $order = $orderService->updateStatusForSeller((string) $data['seller']['store']->id, $data['order']->id, OrderStatus::Packing);
    $order = $orderService->updateStatusForSeller((string) $data['seller']['store']->id, $order->id, OrderStatus::ReadyToShip);

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/orders/'.$order->id.'/status', ['status' => 'shipped'])
        ->assertUnprocessable();
});

test('non owner seller cannot view order', function () {
    $data = createPaidOrderForBuyer();
    $otherSeller = createSellerWithStore();

    test()->withToken($otherSeller['token'])
        ->getJson('/api/v1/seller/orders/'.$data['order']->id)
        ->assertNotFound();
});

test('seller transitions shipped to delivered', function () {
    $data = createPaidOrderForBuyer();
    $orderService = app(OrderServiceInterface::class);
    $order = $orderService->updateStatusForSeller((string) $data['seller']['store']->id, $data['order']->id, OrderStatus::Packing);
    $order = $orderService->updateStatusForSeller((string) $data['seller']['store']->id, $order->id, OrderStatus::ReadyToShip);
    $order = $orderService->updateStatusForSeller((string) $data['seller']['store']->id, $order->id, OrderStatus::Shipped, 'UZ999', 'UzPost');

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/orders/'.$order->id.'/status', ['status' => 'delivered'])
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered');
});

test('fulfillment events dispatched', function () {
    Event::fake([OrderPackingStarted::class, OrderShipped::class]);
    $data = createPaidOrderForBuyer();

    test()->withToken($data['seller']['token'])
        ->putJson('/api/v1/seller/orders/'.$data['order']->id.'/status', ['status' => 'packing']);

    Event::assertDispatched(OrderPackingStarted::class);
});
