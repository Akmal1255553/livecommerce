<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Minor units, same convention as orders.total.
            $table->bigInteger('available_balance')->default(0);
            // Funds locked by a withdrawal that has not settled yet.
            $table->bigInteger('held_balance')->default(0);
            $table->string('currency', 3)->default('UZS');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            // Signed: positive credits the wallet, negative debits it.
            $table->bigInteger('amount');
            $table->bigInteger('balance_after')->nullable();
            $table->string('currency', 3)->default('UZS');
            $table->string('method', 20)->nullable();
            $table->string('reference')->nullable()->index();
            $table->uuid('related_id')->nullable()->index();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->unique(['wallet_id', 'reference']);
        });

        Schema::create('wallet_withdrawals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);
            $table->string('currency', 3)->default('UZS');
            $table->string('method', 20);
            // Only the masked tail is stored; full PAN never touches our database.
            $table->string('card_last4', 4);
            $table->string('card_holder', 120);
            $table->string('status', 20)->default('requested');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
