<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'logo_url' => null,
            'description' => fake()->sentence(),
            'status' => StoreStatus::Pending,
            'commission_rate' => 10.00,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreStatus::Active,
        ]);
    }
}
