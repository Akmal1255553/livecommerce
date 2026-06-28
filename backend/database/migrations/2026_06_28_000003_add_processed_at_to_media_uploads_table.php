<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_uploads', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('media_uploads', function (Blueprint $table) {
            $table->dropColumn('processed_at');
        });
    }
};
