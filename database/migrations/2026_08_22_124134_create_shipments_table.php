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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->cascadeOnDelete();
            // Marketplace mein shipment seller-wise ho sakta hai
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            // Shipment status from master_types
            // pending, processing, shipped, delivered, returned
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Courier information
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 150)->nullable();
            $table->string('tracking_url', 500)->nullable();
            // Shipment charges
            $table->decimal('shipping_amount', 15, 2)->default(0);
            // Important shipment dates
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('seller_id');
            $table->index('status_id');
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
