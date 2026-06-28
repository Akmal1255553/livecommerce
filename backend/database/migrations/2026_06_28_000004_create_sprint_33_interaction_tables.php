<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'video_id']);
            $table->index('video_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->text('body');
            $table->unsignedInteger('like_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['video_id', 'created_at']);
            $table->index('parent_id');
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'video_id']);
        });

        Schema::create('video_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['video_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_shares');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('video_likes');
    }
};
