<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'video_url' => 'https://cdn.example.com/videos/'.fake()->uuid().'/playlist.m3u8',
            'thumbnail_url' => 'https://cdn.example.com/videos/'.fake()->uuid().'/thumb.jpg',
            'duration' => fake()->numberBetween(15, 60),
            'width' => 1080,
            'height' => 1920,
            'view_count' => fake()->numberBetween(0, 10000),
            'like_count' => fake()->numberBetween(0, 1000),
            'comment_count' => fake()->numberBetween(0, 100),
            'share_count' => 0,
            'status' => VideoStatus::Published,
            'visibility' => VideoVisibility::Public,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::Published,
            'visibility' => VideoVisibility::Public,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => VideoStatus::Uploading,
        ]);
    }
}
