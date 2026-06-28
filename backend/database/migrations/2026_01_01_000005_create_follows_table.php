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
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('follower_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('following_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['follower_id', 'following_id']);
            $table->index('follower_id', 'idx_follows_follower');
            $table->index('following_id', 'idx_follows_following');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE follows ADD CONSTRAINT follows_no_self_follow CHECK (follower_id != following_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
