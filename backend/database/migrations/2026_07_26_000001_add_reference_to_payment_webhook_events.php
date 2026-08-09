<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Webhooks now also settle wallet top-ups, whose merchant reference is not a
     * bare order UUID. `order_id` keeps the uuid type for order lookups; the raw
     * provider reference lives alongside it.
     */
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->string('reference')->nullable()->after('transaction_id')->index();
        });

        $orderId = DB::connection()->getDriverName() === 'pgsql'
            ? DB::raw('order_id::text')
            : DB::raw('order_id');

        DB::table('payment_webhook_events')
            ->whereNull('reference')
            ->whereNotNull('order_id')
            ->update(['reference' => $orderId]);
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropColumn('reference');
        });
    }
};
