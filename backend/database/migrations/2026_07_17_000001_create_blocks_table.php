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
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('blocker_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['blocker_id', 'blocked_id']);
            $table->index('blocker_id', 'idx_blocks_blocker');
            $table->index('blocked_id', 'idx_blocks_blocked');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE blocks ADD CONSTRAINT blocks_no_self_block CHECK (blocker_id != blocked_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
