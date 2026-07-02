<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Video;
use App\Models\VideoProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VideoProduct>
 */
class VideoProductFactory extends Factory
{
    protected $model = VideoProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'video_id' => Video::factory()->published(),
            'product_id' => Product::factory(),
            'sort_order' => 0,
            'is_featured' => false,
            'starts_at' => null,
            'ends_at' => null,
            'position_x' => null,
            'position_y' => null,
            'product_version' => 1,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
