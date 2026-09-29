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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnUpdate()->cascadeOnDelete();
            // Stock movement type
            // Example:
            // purchase, sale, return, damage, adjustment, transfer
            $table->foreignId('type_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Positive = stock added
            // Negative = stock removed
            $table->integer('quantity');
            // Stock before this transaction
            $table->unsignedInteger('quantity_before');
            // Stock after this transaction
            $table->unsignedInteger('quantity_after');
            // Optional reference information
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('inventory_id');
            $table->index('type_id');
            $table->index([
                'reference_type',
                'reference_id',
            ]);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
