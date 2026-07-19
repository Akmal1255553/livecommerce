<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Video;
use App\Models\VideoProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Idempotent demo catalog for cloud MVP (no factories / Faker — works with composer --no-dev).
 */
class DemoCommerceSeeder extends Seeder
{
    public function run(): void
    {
        if (Product::query()->exists()) {
            $this->command?->info('DemoCommerceSeeder: products already present — skip.');

            return;
        }

        $seller = User::query()->create([
            'username' => 'demoseller',
            'email' => 'demoseller@livecommerce.local',
            'password' => Hash::make('Password1!'),
            'role' => UserRole::Seller,
            'status' => UserStatus::Active,
            'locale' => 'uz',
            'email_verified_at' => now(),
        ]);

        UserProfile::query()->create([
            'user_id' => $seller->id,
            'display_name' => 'Demo Seller',
            'notification_settings' => [],
        ]);

        $store = Store::query()->create([
            'user_id' => $seller->id,
            'name' => 'Demo Store',
            'slug' => 'demo-store',
            'description' => 'Seeded store for Phase A E2E',
            'status' => StoreStatus::Active,
            'commission_rate' => 10,
        ]);

        $category = Category::query()->create([
            'name' => 'Demo Category',
            'slug' => 'demo-category',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'title' => 'Demo Sneakers',
            'description' => 'Seeded product for Phase A E2E on Render.',
            'price' => 199000,
            'compare_at_price' => 249000,
            'sku' => 'DEMO-SNEAKER-001',
            'stock_quantity' => 100,
            'status' => ProductStatus::Active,
            'rating_avg' => 4.5,
            'review_count' => 12,
            'version' => 1,
        ]);

        $product->images()->create([
            'url' => 'https://picsum.photos/seed/demo-sneaker/800/800',
            'sort_order' => 0,
        ]);

        $creator = User::query()->create([
            'username' => 'democreator',
            'email' => 'democreator@livecommerce.local',
            'password' => Hash::make('Password1!'),
            'role' => UserRole::User,
            'status' => UserStatus::Active,
            'locale' => 'uz',
            'email_verified_at' => now(),
        ]);

        UserProfile::query()->create([
            'user_id' => $creator->id,
            'display_name' => 'Demo Creator',
            'notification_settings' => [],
        ]);

        $video = Video::query()->create([
            'user_id' => $creator->id,
            'title' => 'Demo live commerce clip',
            'description' => 'Tap the product overlay to buy.',
            'video_url' => 'https://cdn.example.com/videos/'.Str::uuid().'/playlist.m3u8',
            'thumbnail_url' => 'https://picsum.photos/seed/livecommerce/720/1280',
            'duration' => 30,
            'width' => 1080,
            'height' => 1920,
            'view_count' => 0,
            'like_count' => 0,
            'comment_count' => 0,
            'share_count' => 0,
            'status' => VideoStatus::Published,
            'visibility' => VideoVisibility::Public,
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
            'product_version' => 1,
        ]);

        $this->command?->info("DemoCommerceSeeder: product={$product->id} video={$video->id}");
    }
}
