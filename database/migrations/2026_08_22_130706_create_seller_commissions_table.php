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
        Schema::create('seller_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            // Commission type from master_types
            // percentage / fixed
            $table->foreignId('commission_type_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Original item/order amount
            $table->decimal('sale_amount', 15, 2);
            // Commission rate/value
            $table->decimal('commission_rate', 15, 2)->default(0);
            // Actual platform commission
            $table->decimal('commission_amount', 15, 2)->default(0);
            // Amount seller will receive
            $table->decimal('seller_amount', 15, 2)->default(0);
            // Commission status
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('seller_id');
            $table->index('order_id');
            $table->index('order_item_id');
            $table->index('commission_type_id');
            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_commissions');
    }
};
