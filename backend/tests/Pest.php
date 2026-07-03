<?php

declare(strict_types=1);

use App\Contracts\Services\OrderServiceInterface;
use App\DTOs\Order\CreateOrderData;
use App\DTOs\Order\OrderLineSnapshot;
use App\DTOs\Order\OrderTotals;
use App\DTOs\Order\PaymentSnapshot;
use App\DTOs\Order\ShipmentSnapshot;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\Video;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * @return array{user_id: string, access_token: string}
 */
function registerUser(string $username, string $email): array
{
    $response = test()->postJson('/api/v1/auth/register', [
        'username' => $username,
        'email' => $email,
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertCreated();

    return [
        'user_id' => $response->json('data.user.id'),
        'access_token' => $response->json('data.access_token'),
    ];
}

function analyticsSession(): string
{
    return (string) Str::uuid();
}

function publishedVideo(?User $owner = null): Video
{
    $owner ??= User::factory()->create();

    return Video::factory()->for($owner)->published()->create([
        'like_count' => 0,
        'comment_count' => 0,
        'share_count' => 0,
        'view_count' => 0,
    ]);
}

/**
 * @return array{user: User, store: Store, token: string}
 */
function createSellerWithStore(): array
{
    $user = User::factory()->seller()->create();
    $store = Store::factory()->for($user)->active()->create();

    $auth = test()->postJson('/api/v1/auth/login', [
        'login' => $user->email,
        'password' => 'password',
    ])->assertOk();

    return [
        'user' => $user,
        'store' => $store,
        'token' => $auth->json('data.access_token'),
    ];
}

/**
 * @return array{buyer: User, buyer_token: string, seller: array, product: Product, order: Order}
 */
function createPaidOrderForBuyer(?User $buyer = null, ?Product $product = null): array
{
    $seller = createSellerWithStore();
    $product ??= Product::factory()->for($seller['store'])->create([
        'stock_quantity' => 50,
        'price' => 250000,
    ]);
    $buyer ??= User::factory()->create();
    $buyerAuth = registerUser('buyer'.uniqid(), 'buyer'.uniqid().'@example.com');

    $orderService = app(OrderServiceInterface::class);

    $unitPrice = Money::uzs(250000);
    $line = new OrderLineSnapshot(
        productId: $product->id,
        variantId: null,
        productTitle: $product->title,
        variantName: null,
        sku: $product->sku,
        quantity: 1,
        unitPrice: $unitPrice,
        discount: Money::zero(),
        lineTotal: $unitPrice,
    );

    $totals = new OrderTotals(
        subtotal: $unitPrice,
        shipping: Money::uzs(25000),
        discount: Money::zero(),
        tax: Money::zero(),
        total: Money::uzs(275000),
    );

    $order = $orderService->createFromCheckout(new CreateOrderData(
        userId: $buyerAuth['user_id'],
        storeId: $seller['store']->id,
        lines: [$line],
        totals: $totals,
        payment: new PaymentSnapshot(
            provider: 'fake',
            method: 'fake',
            transactionId: null,
            amount: Money::uzs(275000),
            status: PaymentStatus::Pending->value,
        ),
        shipment: new ShipmentSnapshot(
            address: [
                'full_name' => 'Test Buyer',
                'phone' => '+998901234567',
                'region' => 'Tashkent',
                'city' => 'Tashkent',
                'address_line' => '123 Test St',
                'postal_code' => '100000',
            ],
        ),
    ));

    $order = $orderService->markAwaitingPayment($order);
    $order = $orderService->markPaid($order);

    return [
        'buyer' => User::find($buyerAuth['user_id']),
        'buyer_token' => $buyerAuth['access_token'],
        'seller' => $seller,
        'product' => $product,
        'order' => $order,
    ];
}
