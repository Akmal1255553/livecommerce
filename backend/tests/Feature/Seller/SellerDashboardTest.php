<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

test('user can apply to become a seller with pending store', function () {
    $auth = registerUser('newseller', 'newseller@example.com');

    $response = test()->withToken($auth['access_token'])
        ->postJson('/api/v1/seller/apply', [
            'store_name' => 'Fashion Hub',
            'description' => 'Trendy fashion store',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Fashion Hub')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.slug', 'fashion-hub');

    $user = User::query()->findOrFail($auth['user_id']);
    expect($user->role)->toBe(UserRole::Seller);

    test()->withToken($auth['access_token'])
        ->postJson('/api/v1/seller/apply', [
            'store_name' => 'Another Store',
        ])
        ->assertStatus(409);
});

test('seller dashboard and analytics require seller middleware', function () {
    $buyer = registerUser('dashbuyer', 'dashbuyer@example.com');

    test()->withToken($buyer['access_token'])
        ->getJson('/api/v1/seller/dashboard')
        ->assertForbidden();

    $seller = createSellerWithStore();
    $category = Category::factory()->create();

    Product::factory()->for($seller['store'])->create([
        'category_id' => $category->id,
        'status' => ProductStatus::Active,
        'stock_quantity' => 3,
        'price' => 100000,
    ]);

    Order::factory()->for($seller['store'])->create([
        'user_id' => User::factory(),
        'status' => OrderStatus::Paid,
        'total' => 100000,
        'subtotal' => 100000,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
    ]);

    test()->withToken($seller['token'])
        ->getJson('/api/v1/seller/dashboard')
        ->assertOk()
        ->assertJsonPath('data.total_products', 1)
        ->assertJsonPath('data.low_stock_products', 1)
        ->assertJsonPath('data.total_orders', 1)
        ->assertJsonPath('data.total_revenue', 100000)
        ->assertJsonPath('data.store.slug', $seller['store']->slug);

    test()->withToken($seller['token'])
        ->getJson('/api/v1/seller/analytics/summary?period=last_30_days')
        ->assertOk()
        ->assertJsonPath('data.period', 'last_30_days')
        ->assertJsonPath('data.total_orders', 1)
        ->assertJsonPath('data.total_revenue', 100000)
        ->assertJsonStructure(['data' => ['series', 'currency', 'total_products']]);
});

test('public storefront returns store and products by slug', function () {
    $seller = createSellerWithStore();
    $category = Category::factory()->create();

    Product::factory()->for($seller['store'])->create([
        'category_id' => $category->id,
        'title' => 'Public Tee',
        'status' => ProductStatus::Active,
        'stock_quantity' => 10,
    ]);

    Product::factory()->for($seller['store'])->create([
        'category_id' => $category->id,
        'title' => 'Draft Hidden',
        'status' => ProductStatus::Draft,
        'stock_quantity' => 10,
    ]);

    test()->getJson('/api/v1/stores/'.$seller['store']->slug)
        ->assertOk()
        ->assertJsonPath('data.name', $seller['store']->name)
        ->assertJsonPath('data.slug', $seller['store']->slug);

    test()->getJson('/api/v1/stores/'.$seller['store']->slug.'/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Public Tee');

    test()->getJson('/api/v1/stores/missing-store-slug')
        ->assertNotFound();
});
