<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('title');
            $table->string('channel_id');
            $table->string('stream_key')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->unsignedInteger('viewer_count')->default(0);
            $table->string('replay_url', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
            $table->index(['seller_id', 'created_at']);
        });

        Schema::create('live_session_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_at')->nullable();
            $table->unsignedInteger('offset_seconds')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['live_session_id', 'product_id']);
            $table->index(['live_session_id', 'is_pinned']);
        });

        Schema::create('live_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 500);
            $table->string('type', 20)->default('user');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['live_session_id', 'created_at']);
        });

        Schema::create('live_viewer_metrics', function (Blueprint $table): void {
            $table->uuid('live_session_id')->primary();
            $table->unsignedInteger('current_viewers')->default(0);
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('unique_viewers')->default(0);
            $table->timestamps();

            $table->foreign('live_session_id')
                ->references('id')
                ->on('live_sessions')
                ->cascadeOnDelete();
        });

        Schema::create('live_viewer_presence', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();

            $table->unique(['live_session_id', 'user_id']);
            $table->index(['live_session_id', 'left_at']);
        });

        Schema::create('live_analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->jsonb('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['live_session_id', 'event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_analytics_events');
        Schema::dropIfExists('live_viewer_presence');
        Schema::dropIfExists('live_viewer_metrics');
        Schema::dropIfExists('live_chat_messages');
        Schema::dropIfExists('live_session_products');
        Schema::dropIfExists('live_sessions');
    }
};
