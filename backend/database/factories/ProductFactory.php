<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(50_000, 500_000);

        return [
            'store_id' => Store::factory()->active(),
            'category_id' => Category::factory(),
            'brand_id' => null,
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'price' => $price,
            'compare_at_price' => $price + fake()->numberBetween(10_000, 100_000),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'stock_quantity' => fake()->numberBetween(1, 100),
            'status' => ProductStatus::Active,
            'rating_avg' => fake()->randomFloat(2, 3, 5),
            'review_count' => fake()->numberBetween(0, 50),
        ];
    }

    public function withBrand(): static
    {
        return $this->state(fn (array $attributes) => [
            'brand_id' => Brand::factory(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::OutOfStock,
            'stock_quantity' => 0,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $product->images()->create([
                'url' => 'https://cdn.example.com/products/'.$product->id.'/1.jpg',
                'sort_order' => 0,
            ]);

            $product->variants()->createMany([
                [
                    'name' => 'Size',
                    'value' => 'S',
                    'sku' => $product->sku.'-S',
                    'price_adjustment' => 0,
                    'stock_quantity' => 10,
                ],
                [
                    'name' => 'Size',
                    'value' => 'M',
                    'sku' => $product->sku.'-M',
                    'price_adjustment' => 0,
                    'stock_quantity' => 20,
                ],
            ]);
        });
    }
}
