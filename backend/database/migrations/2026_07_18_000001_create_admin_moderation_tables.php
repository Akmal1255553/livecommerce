<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 50);
            $table->string('entity_id', 36);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id'], 'idx_audit_logs_entity');
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user');
            $table->index('created_at', 'idx_audit_logs_created');
        });

        Schema::create('content_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('target_type', 30);
            $table->string('target_id', 36);
            $table->string('reason', 500);
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_content_reports_status');
            $table->index(['target_type', 'target_id'], 'idx_content_reports_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('audit_logs');
    }
};
