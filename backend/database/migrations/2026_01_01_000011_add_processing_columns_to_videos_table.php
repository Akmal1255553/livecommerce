<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->timestamp('processing_started_at')->nullable()->after('visibility');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_started_at');
            $table->timestamp('published_at')->nullable()->after('processing_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'processing_started_at',
                'processing_completed_at',
                'published_at',
            ]);
        });
    }
};
