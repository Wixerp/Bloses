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
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones')->cascadeOnUpdate()->restrictOnDelete();
            // Base shipping charge
            $table->decimal('base_rate', 15, 2)->default(0);
            // Additional charge according to weight
            $table->decimal('per_kg_rate', 15, 2)->default(0);
            // Free shipping above this order amount
            $table->decimal('free_shipping_min_amount', 15, 2)->nullable();
            // Maximum weight supported by this rate
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->string('currency', 10)->default('PKR');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique([
                'shipping_method_id',
                'shipping_zone_id',
            ]);
            $table->index('shipping_method_id');
            $table->index('shipping_zone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
