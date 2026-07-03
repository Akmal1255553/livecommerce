<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100000, 500000);
        $shipping = 25000;

        return [
            'id' => (string) Str::uuid(),
            'order_number' => 'LC-'.now()->format('Ymd').'-'.str_pad((string) fake()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'status' => OrderStatus::Pending,
            'version' => 1,
            'subtotal' => $subtotal,
            'shipping_cost' => $shipping,
            'discount' => 0,
            'tax' => 0,
            'total' => $subtotal + $shipping,
            'currency' => 'UZS',
            'shipping_address' => [
                'full_name' => fake()->name(),
                'phone' => '+998901234567',
                'region' => 'Tashkent',
                'city' => 'Tashkent',
                'address_line' => fake()->streetAddress(),
                'postal_code' => '100000',
            ],
            'payment_method' => 'fake',
            'payment_provider' => 'fake',
            'payment_status' => PaymentStatus::Pending,
        ];
    }
}
