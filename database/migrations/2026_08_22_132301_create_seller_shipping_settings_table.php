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
        Schema::create('seller_shipping_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->cascadeOnUpdate()->restrictOnDelete();
            $table->boolean('is_enabled')->default(true)->index();
            // Seller can offer free shipping above this amount
            $table->decimal('free_shipping_min_amount', 15, 2)->nullable();
            // Seller-specific handling charge
            $table->decimal('handling_fee', 15, 2)->default(0);
            // Seller-specific estimated delivery days
            $table->unsignedInteger('min_delivery_days')->nullable();
            $table->unsignedInteger('max_delivery_days')->nullable();
            $table->timestamps();
            $table->unique([
                'seller_id',
                'shipping_method_id',
            ]);
            $table->index('seller_id');
            $table->index('shipping_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_shipping_settings');
    }
};
