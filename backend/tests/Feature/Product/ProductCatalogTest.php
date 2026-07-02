<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

test('products list returns active catalog items', function () {
    Product::factory()->count(2)->create();
    Product::factory()->draft()->create();

    test()->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                ['id', 'title', 'price', 'currency', 'sku', 'stock_quantity', 'status', 'images', 'variants'],
            ],
            'meta' => ['currentPage', 'lastPage', 'perPage', 'total'],
        ]);
});

test('product detail returns full resource', function () {
    $product = Product::factory()->withBrand()->create();

    test()->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.brand.id', $product->brand_id)
        ->assertJsonPath('data.discount_percent', $product->discountPercent());
});

test('product search filters by query and category', function () {
    $category = Category::factory()->create();
    Product::factory()->create([
        'category_id' => $category->id,
        'title' => 'Summer Dress',
        'status' => ProductStatus::Active,
    ]);
    Product::factory()->create(['title' => 'Winter Coat']);

    test()->getJson('/api/v1/products/search?q=dress&category_id='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Summer Dress');
});

test('categories tree returns nested children', function () {
    $parent = Category::factory()->create(['name' => 'Fashion', 'slug' => 'fashion-tree']);
    Category::factory()->create([
        'parent_id' => $parent->id,
        'name' => 'Dresses',
        'slug' => 'dresses-tree',
    ]);

    test()->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'fashion-tree'])
        ->assertJsonFragment(['slug' => 'dresses-tree']);
});

test('category products endpoint returns products in category', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    Product::factory()->create();

    test()->getJson("/api/v1/categories/{$category->id}/products")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $product->id);
});

test('brands list returns active brands', function () {
    Brand::factory()->count(2)->create();

    test()->getJson('/api/v1/brands')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
