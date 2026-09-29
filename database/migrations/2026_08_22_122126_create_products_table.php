<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Store that owns/list this product
            $table->foreignId('store_id')->constrained('stores')->cascadeOnUpdate()->cascadeOnDelete();
            // Main category
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            // Brand
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('name', 255);
            $table->string('slug', 280);
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            // Product-level identifiers
            $table->string('product_code', 100)->nullable();
            $table->string('model_number', 100)->nullable();
            $table->string('manufacturer', 150)->nullable();
            // Product status from master_types
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id','slug',]);
            $table->index('store_id');
            $table->index('category_id');
            $table->index('brand_id');
            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
