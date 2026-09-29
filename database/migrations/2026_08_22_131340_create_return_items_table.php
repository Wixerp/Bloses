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
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnUpdate()->cascadeOnDelete();
            // Requested return quantity
            $table->unsignedInteger('quantity');
            // Amount associated with returned quantity
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_amount', 15, 2);
            // Reason can be different for each item
            $table->foreignId('reason_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->unique([
                'return_id',
                'order_item_id',
            ]);
            $table->index('return_id');
            $table->index('order_item_id');
            $table->index('reason_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
