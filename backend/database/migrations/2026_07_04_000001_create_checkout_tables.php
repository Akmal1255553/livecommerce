<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reservation_group_id');
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('status', 20);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'expires_at']);
            $table->index('reservation_group_id');
        });

        Schema::create('checkout_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('request_hash', 64);
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->jsonb('response_json');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_idempotency_keys');
        Schema::dropIfExists('inventory_reservations');
    }
};
