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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnUpdate()->cascadeOnDelete();
            // Current physical stock
            $table->unsignedInteger('quantity')->default(0);
            // Stock reserved for pending orders
            $table->unsignedInteger('reserved_quantity')->default(0);
            // Damaged/unusable stock
            $table->unsignedInteger('damaged_quantity')->default(0);
            // Minimum stock level before reorder
            $table->unsignedInteger('reorder_level')->default(0);
            // Maximum desired stock
            $table->unsignedInteger('reorder_quantity')->default(0);
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamps();
            // One variant gets one inventory record
            // per warehouse.
            $table->unique(['warehouse_id','product_variant_id',]);
            $table->index('warehouse_id');
            $table->index('product_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
