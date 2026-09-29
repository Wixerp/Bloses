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
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            // Shipping method type from master_types
            // e.g. standard, express, same_day
            $table->foreignId('type_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->decimal('base_price', 15, 2)->default(0);
            $table->decimal('price_per_kg', 15, 2)->default(0);
            $table->unsignedInteger('estimated_min_days')->nullable();
            $table->unsignedInteger('estimated_max_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index('type_id');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
