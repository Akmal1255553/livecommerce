<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->uuid('session_id');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at'], 'idx_engagement_events_type');
            $table->index(['video_id', 'event_type', 'created_at'], 'idx_engagement_events_video');
            $table->index(['user_id', 'created_at'], 'idx_engagement_events_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_events');
    }
};
