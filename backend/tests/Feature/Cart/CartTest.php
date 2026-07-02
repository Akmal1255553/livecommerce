<?php

declare(strict_types=1);

use App\Events\CartItemAdded;
use App\Events\CartMerged;
use App\Events\CartUpdated;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * @return array{user: User, store: Store, token: string, product: Product, category: Category}
 */
function createSellerProductForCart(): array
{
    $seller = createSellerWithStore();
    $category = Category::factory()->create();
    $product = Product::factory()->for($seller['store'])->create([
        'category_id' => $category->id,
        'stock_quantity' => 50,
        'price' => 250000,
        'compare_at_price' => 350000,
    ]);

    return [
        'user' => $seller['user'],
        'store' => $seller['store'],
        'token' => $seller['token'],
        'product' => $product,
        'category' => $category,
    ];
}

test('guest can create token and add item', function () {
    $data = createSellerProductForCart();

    $guest = test()->postJson('/api/v1/cart/guest')
        ->assertCreated()
        ->assertJsonPath('data.type', 'guest')
        ->assertJsonPath('data.version', 1);

    $token = $guest->json('data.guest_cart_token');

    test()->withHeader('X-Guest-Cart-Token', $token)
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.unit_price.amount', 250000)
        ->assertJsonPath('data.items.0.unit_price.currency', 'UZS');
});

test('authenticated user can add item to cart', function () {
    $data = createSellerProductForCart();
    $buyer = registerUser('cartbuyer', 'cartbuyer@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.type', 'user')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.summary.item_count', 1);
});

test('adding same product merges quantity', function () {
    $data = createSellerProductForCart();
    $buyer = registerUser('cartmergeqty', 'cartmergeqty@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->assertOk();

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 2,
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 3);
});

test('update quantity increments cart version', function () {
    $data = createSellerProductForCart();
    $buyer = registerUser('cartupdate', 'cartupdate@example.com');

    $itemId = test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->assertOk()
        ->json('data.items.0.id');

    test()->withToken($buyer['access_token'])
        ->putJson("/api/v1/cart/items/{$itemId}", ['quantity' => 4])
        ->assertOk()
        ->assertJsonPath('data.version', 3)
        ->assertJsonPath('data.items.0.quantity', 4);
});

test('remove item and clear cart', function () {
    $data = createSellerProductForCart();
    $buyer = registerUser('cartremove', 'cartremove@example.com');

    $itemId = test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->json('data.items.0.id');

    test()->withToken($buyer['access_token'])
        ->deleteJson("/api/v1/cart/items/{$itemId}")
        ->assertOk()
        ->assertJsonPath('data.items', []);

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ]);

    test()->withToken($buyer['access_token'])
        ->deleteJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.summary.item_count', 0);
});

test('insufficient stock on add returns 422', function () {
    $data = createSellerProductForCart();
    $data['product']->update(['stock_quantity' => 1]);
    $buyer = registerUser('cartstock', 'cartstock@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 5,
        ])
        ->assertUnprocessable();
});

test('inactive product cannot be added to cart', function () {
    $seller = createSellerWithStore();
    $product = Product::factory()->for($seller['store'])->draft()->create();
    $buyer = registerUser('cartdraft', 'cartdraft@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertUnprocessable();
});

test('cart mutation requires auth or guest token', function () {
    $data = createSellerProductForCart();

    test()->postJson('/api/v1/cart/items', [
        'product_id' => $data['product']->id,
        'quantity' => 1,
    ])->assertUnauthorized();
});

test('guest cart merges on login', function () {
    Event::fake([CartMerged::class]);

    $data = createSellerProductForCart();

    $guest = test()->postJson('/api/v1/cart/guest')->assertCreated();
    $token = $guest->json('data.guest_cart_token');

    test()->withHeader('X-Guest-Cart-Token', $token)
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 2,
        ])
        ->assertOk();

    $buyer = registerUser('cartloginmerge', 'cartloginmerge@example.com');

    test()->withHeader('X-Guest-Cart-Token', $token)
        ->withToken($buyer['access_token'])
        ->postJson('/api/v1/auth/login', [
            'login' => 'cartloginmerge@example.com',
            'password' => 'SecurePass123!',
        ])
        ->assertOk();

    Event::assertDispatched(CartMerged::class);

    test()->withToken($buyer['access_token'])
        ->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 2);
});

test('cart events fire on add', function () {
    Event::fake([CartItemAdded::class, CartUpdated::class]);

    $data = createSellerProductForCart();
    $buyer = registerUser('cartevents', 'cartevents@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 1,
        ])
        ->assertOk();

    Event::assertDispatched(CartItemAdded::class);
    Event::assertDispatched(CartUpdated::class);
});

test('max quantity per line is enforced', function () {
    $data = createSellerProductForCart();
    $data['product']->update(['stock_quantity' => 200]);
    $buyer = registerUser('cartmax', 'cartmax@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'quantity' => 100,
        ])
        ->assertUnprocessable();
});

test('variant line uses price adjustment in money totals', function () {
    $data = createSellerProductForCart();
    $variant = $data['product']->variants()->first();
    $variant?->update(['price_adjustment' => 10000, 'stock_quantity' => 20]);
    $buyer = registerUser('cartvariant', 'cartvariant@example.com');

    test()->withToken($buyer['access_token'])
        ->postJson('/api/v1/cart/items', [
            'product_id' => $data['product']->id,
            'variant_id' => $variant?->id,
            'quantity' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('data.items.0.unit_price.amount', 260000);
});

test('expired guest cart returns empty state', function () {
    $token = (string) Str::uuid();
    Redis::del('cart:guest:'.$token);

    test()->withHeader('X-Guest-Cart-Token', $token)
        ->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.version', 0)
        ->assertJsonPath('data.items', []);
});
