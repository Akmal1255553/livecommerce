<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $fashion = Category::query()->firstOrCreate(
            ['slug' => 'fashion'],
            ['name' => 'Fashion', 'sort_order' => 1, 'is_active' => true],
        );

        Category::query()->firstOrCreate(
            ['slug' => 'dresses'],
            [
                'parent_id' => $fashion->id,
                'name' => 'Dresses',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        Brand::query()->firstOrCreate(
            ['slug' => 'live-style'],
            ['name' => 'LiveStyle', 'is_active' => true],
        );

        if (Product::query()->exists()) {
            return;
        }

        Product::factory()->count(5)->create();
    }
}
