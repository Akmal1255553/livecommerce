<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payme drives a two-phase protocol (CreateTransaction then PerformTransaction) and may
     * ask about a transaction long after it ended, so its state has to outlive the request.
     * Times are epoch milliseconds because that is what the protocol exchanges.
     */
    public function up(): void
    {
        Schema::create('payme_transactions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('payme_transaction_id')->unique();
            $table->string('reference')->index();
            $table->bigInteger('amount')->comment('tiyin, as sent by Payme');
            $table->smallInteger('state')->default(1);
            $table->integer('reason')->nullable();
            $table->bigInteger('payme_time');
            $table->bigInteger('create_time')->index();
            $table->bigInteger('perform_time')->nullable();
            $table->bigInteger('cancel_time')->nullable();
            $table->timestamps();

            $table->index(['reference', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payme_transactions');
    }
};
