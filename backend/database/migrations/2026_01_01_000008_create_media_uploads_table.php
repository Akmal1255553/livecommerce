<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('entity_type', 50);
            $table->uuid('entity_id');
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('storage_path', 500);
            $table->string('checksum', 128)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_media_uploads_status');
            $table->index(['entity_type', 'entity_id'], 'idx_media_uploads_entity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
