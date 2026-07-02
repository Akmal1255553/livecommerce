<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Events\ProductAttachedToVideo;
use App\Models\Product;
use App\Models\Video;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

test('get video products returns empty list for untagged video', function () {
    $video = publishedVideo();

    test()->getJson("/api/v1/videos/{$video->id}/products")
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('seller can attach reorder and clear products on video', function () {
    Event::fake([ProductAttachedToVideo::class]);

    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create(['duration' => 60]);
    $productA = Product::factory()->for($seller['store'])->create(['title' => 'Dress A']);
    $productB = Product::factory()->for($seller['store'])->create(['title' => 'Dress B']);

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                [
                    'product_id' => $productA->id,
                    'sort_order' => 1,
                    'is_featured' => false,
                    'starts_at' => 5,
                    'ends_at' => 30,
                    'position_x' => 72.5,
                    'position_y' => 85,
                ],
                [
                    'product_id' => $productB->id,
                    'sort_order' => 0,
                    'is_featured' => true,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.product.title', 'Dress B')
        ->assertJsonPath('data.0.is_featured', true)
        ->assertJsonPath('data.1.product.title', 'Dress A')
        ->assertJsonPath('data.1.starts_at', 5)
        ->assertJsonPath('data.1.product_version', 1);

    Event::assertDispatched(ProductAttachedToVideo::class, function (ProductAttachedToVideo $event) use ($video, $seller, $productB): bool {
        return $event->videoId === $video->id
            && $event->userId === $seller['user']->id
            && $event->featuredProductId === $productB->id;
    });

    test()->getJson("/api/v1/videos/{$video->id}/products")
        ->assertOk()
        ->assertJsonPath('data.0.product.is_purchasable', true)
        ->assertJsonPath('data.0.product.currency', 'UZS');

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", ['products' => []])
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('first product becomes featured when none specified', function () {
    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $product = Product::factory()->for($seller['store'])->create();

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $product->id],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.is_featured', true);
});

test('video detail and feed include product overlay payload', function () {
    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $product = Product::factory()->for($seller['store'])->create(['title' => 'Overlay Product']);

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $product->id, 'is_featured' => true],
            ],
        ])
        ->assertOk();

    test()->getJson("/api/v1/videos/{$video->id}")
        ->assertOk()
        ->assertJsonPath('data.products.0.product.title', 'Overlay Product');

    test()->getJson('/api/v1/feed/new?limit=20')
        ->assertOk()
        ->assertJsonPath('data.0.products.0.product.title', 'Overlay Product');
});

test('sync rejects duplicate featured products', function () {
    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $products = Product::factory()->count(2)->for($seller['store'])->create();

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $products[0]->id, 'is_featured' => true],
                ['product_id' => $products[1]->id, 'is_featured' => true],
            ],
        ])
        ->assertUnprocessable();
});

test('sync rejects products not owned by video creator', function () {
    $seller = createSellerWithStore();
    $otherSeller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $foreignProduct = Product::factory()->for($otherSeller['store'])->create();

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $foreignProduct->id],
            ],
        ])
        ->assertUnprocessable();
});

test('sync rejects inactive products', function () {
    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $draft = Product::factory()->for($seller['store'])->draft()->create();

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $draft->id],
            ],
        ])
        ->assertUnprocessable();
});

test('non owner cannot sync products on video', function () {
    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $product = Product::factory()->for($seller['store'])->create();
    $intruder = registerUser('intruder', 'intruder@example.com');

    test()->withToken($intruder['access_token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $product->id],
            ],
        ])
        ->assertForbidden();
});

test('public viewers only see active tagged products', function () {
    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $active = Product::factory()->for($seller['store'])->create(['title' => 'Active']);
    $inactive = Product::factory()->for($seller['store'])->create(['title' => 'Inactive']);

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $active->id, 'sort_order' => 0],
                ['product_id' => $inactive->id, 'sort_order' => 1],
            ],
        ])
        ->assertOk();

    $inactive->update(['status' => ProductStatus::OutOfStock, 'stock_quantity' => 0]);

    Auth::forgetGuards();

    test()->withHeader('Authorization', '')
        ->getJson("/api/v1/videos/{$video->id}/products")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.title', 'Active');

    test()->withToken($seller['token'])
        ->getJson("/api/v1/videos/{$video->id}/products")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('product version is snapshotted on video attach', function () {
    $seller = createSellerWithStore();
    $video = Video::factory()->for($seller['user'])->published()->create();
    $product = Product::factory()->for($seller['store'])->create(['version' => 3]);

    test()->withToken($seller['token'])
        ->putJson("/api/v1/videos/{$video->id}/products", [
            'products' => [
                ['product_id' => $product->id],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.0.product_version', 3);

    $product->update(['version' => 4]);

    test()->getJson("/api/v1/videos/{$video->id}/products")
        ->assertOk()
        ->assertJsonPath('data.0.product_version', 3);
});

test('seller product update increments version', function () {
    $seller = createSellerWithStore();
    $product = Product::factory()->for($seller['store'])->create(['version' => 1]);

    test()->withToken($seller['token'])
        ->putJson("/api/v1/products/{$product->id}", [
            'title' => 'Updated title',
        ])
        ->assertOk();

    expect($product->fresh()?->version)->toBe(2);
});
