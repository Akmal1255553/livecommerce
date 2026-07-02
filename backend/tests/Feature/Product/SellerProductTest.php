<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;

test('seller can create update and delete products', function () {
    $seller = createSellerWithStore();
    $category = Category::factory()->create();

    $create = test()->withToken($seller['token'])
        ->postJson('/api/v1/products', [
            'title' => 'Seller Dress',
            'description' => 'Cotton dress',
            'category_id' => $category->id,
            'price' => 250000,
            'compare_at_price' => 350000,
            'sku' => 'DRS-001',
            'stock_quantity' => 25,
            'status' => ProductStatus::Active->value,
            'images' => ['https://cdn.example.com/products/1.jpg'],
            'variants' => [
                ['name' => 'Size', 'value' => 'M', 'stock_quantity' => 10],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Seller Dress')
        ->assertJsonPath('data.sku', 'DRS-001')
        ->assertJsonPath('data.discount_percent', 29);

    $productId = $create->json('data.id');

    test()->withToken($seller['token'])
        ->getJson('/api/v1/products?mine=1')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    test()->withToken($seller['token'])
        ->putJson("/api/v1/products/{$productId}", [
            'title' => 'Updated Dress',
            'stock_quantity' => 0,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Dress')
        ->assertJsonPath('data.status', ProductStatus::OutOfStock->value);

    test()->withToken($seller['token'])
        ->deleteJson("/api/v1/products/{$productId}")
        ->assertNoContent();

    expect(Product::withTrashed()->find($productId)?->trashed())->toBeTrue();
});

test('seller product mutations require seller role and active store', function () {
    $user = registerUser('buyeronly', 'buyeronly@example.com');

    test()->withToken($user['access_token'])
        ->postJson('/api/v1/products', [
            'title' => 'Blocked',
            'category_id' => 1,
            'price' => 1000,
            'stock_quantity' => 1,
        ])
        ->assertForbidden();
});
