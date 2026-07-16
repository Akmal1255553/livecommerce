<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique();
            $table->string('event');
            $table->string('transaction_id')->index();
            $table->uuid('order_id')->nullable()->index();
            $table->json('payload');
            $table->timestamp('processed_at');
            $table->timestamps();
        });

        Schema::create('seller_payouts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('currency', 3)->default('UZS');
            $table->string('status', 20)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('payment_webhook_events');
    }
};
