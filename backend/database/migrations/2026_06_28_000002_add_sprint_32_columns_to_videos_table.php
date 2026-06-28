<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('codec', 50)->nullable()->after('height');
            $table->unsignedInteger('bitrate')->nullable()->after('codec');
            $table->string('failure_code', 50)->nullable()->after('published_at');
            $table->text('failure_message')->nullable()->after('failure_code');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['codec', 'bitrate', 'failure_code', 'failure_message']);
        });
    }
};
