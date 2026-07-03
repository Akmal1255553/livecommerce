<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->string('date', 8)->primary();
            $table->unsignedInteger('last_sequence')->default(0);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 24)->unique();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->string('status_before_refund', 30)->nullable();
            $table->uuid('active_refund_id')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total');
            $table->char('currency', 3)->default('UZS');
            $table->jsonb('shipping_address');
            $table->string('payment_method', 50);
            $table->string('payment_provider', 50)->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->string('payment_transaction_id', 255)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 255)->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('product_title');
            $table->string('variant_name', 100)->nullable();
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->char('currency', 3)->default('UZS');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('order_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->string('actor_type', 20);
            $table->uuid('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'idempotency_key']);
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('requested');
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('active_refund_id')->references('id')->on('refund_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['active_refund_id']);
        });

        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('order_status_transitions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_number_sequences');
    }
};
