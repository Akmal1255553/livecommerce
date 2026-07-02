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
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1)->after('status');
        });

        Schema::create('video_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->decimal('starts_at', 8, 3)->nullable();
            $table->decimal('ends_at', 8, 3)->nullable();
            $table->decimal('position_x', 5, 2)->nullable();
            $table->decimal('position_y', 5, 2)->nullable();
            $table->unsignedInteger('product_version')->default(1);
            $table->timestamps();

            $table->unique(['video_id', 'product_id']);
            $table->index(['video_id', 'sort_order']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX video_products_one_featured_per_video ON video_products (video_id) WHERE is_featured = true',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('video_products');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
