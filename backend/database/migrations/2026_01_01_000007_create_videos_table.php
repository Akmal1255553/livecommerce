<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->string('raw_video_url', 500)->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);
            $table->string('status', 20)->default('uploading');
            $table->string('visibility', 20)->default('public');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at'], 'idx_videos_feed');
            $table->index(['user_id', 'status', 'created_at'], 'idx_videos_user');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX idx_videos_published ON videos (created_at DESC) WHERE status = 'published' AND deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
