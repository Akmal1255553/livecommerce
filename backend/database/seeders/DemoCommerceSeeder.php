<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent demo catalog for cloud MVP (Render / Supabase).
 * Safe to re-run: skips when an active product already exists.
 */
class DemoCommerceSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            $this->command?->info('DemoCommerceSeeder: products already present — skip.');

            return;
        }

        $seller = User::factory()->seller()->create([
            'username' => 'demoseller',
            'email' => 'demoseller@livecommerce.local',
            'password' => Hash::make('Password1!'),
        ]);

        $store = Store::factory()->for($seller)->active()->create([
            'name' => 'Demo Store',
            'slug' => 'demo-store',
        ]);

        $category = Category::factory()->create([
            'name' => 'Demo Category',
            'slug' => 'demo-category',
        ]);

        $product = Product::factory()->for($store)->create([
            'category_id' => $category->id,
            'title' => 'Demo Sneakers',
            'description' => 'Seeded product for Phase A E2E on Render.',
            'price' => 199000,
            'compare_at_price' => 249000,
            'sku' => 'DEMO-SNEAKER-001',
            'stock_quantity' => 100,
        ]);

        $creator = User::factory()->create([
            'username' => 'democreator',
            'email' => 'democreator@livecommerce.local',
            'password' => Hash::make('Password1!'),
        ]);

        $video = Video::factory()->for($creator)->published()->create([
            'title' => 'Demo live commerce clip',
            'description' => 'Tap the product overlay to buy.',
            'thumbnail_url' => 'https://picsum.photos/seed/livecommerce/720/1280',
            'view_count' => 1200,
            'like_count' => 88,
        ]);

        VideoProduct::query()->create([
            'video_id' => $video->id,
            'product_id' => $product->id,
            'sort_order' => 0,
            'is_featured' => true,
            'starts_at' => null,
            'ends_at' => null,
            'position_x' => 0.5,
            'position_y' => 0.7,
            'product_version' => $product->version ?? 1,
        ]);

        $this->command?->info("DemoCommerceSeeder: product={$product->id} video={$video->id}");
    }
}
