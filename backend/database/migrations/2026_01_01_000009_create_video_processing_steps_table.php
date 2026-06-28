<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_processing_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('video_id')->constrained('videos')->restrictOnDelete();
            $table->string('step', 50);
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['video_id', 'step'], 'idx_video_processing_video_step');
            $table->index(['status', 'created_at'], 'idx_video_processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_processing_steps');
    }
};
