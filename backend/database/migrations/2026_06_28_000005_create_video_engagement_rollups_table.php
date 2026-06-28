<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_engagement_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->timestamp('bucket_hour');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->unsignedInteger('completions')->default(0);
            $table->unsignedInteger('skips')->default(0);
            $table->unsignedInteger('follow_after_watch')->default(0);
            $table->unsignedBigInteger('watch_seconds')->default(0);

            $table->unique(['video_id', 'bucket_hour'], 'idx_video_engagement_rollups_unique');
            $table->index(['bucket_hour'], 'idx_video_engagement_rollups_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_engagement_rollups');
    }
};
