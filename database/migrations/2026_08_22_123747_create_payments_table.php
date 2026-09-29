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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->cascadeOnDelete();
            // Payment status from master_types
            // pending, paid, failed, refunded, cancelled
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Payment method from master_types
            // COD, Card, Bank Transfer, etc.
            $table->foreignId('payment_method_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Amount actually processed
            $table->decimal('amount', 15, 2);

            $table->string('currency', 10)->default('PKR');
            // Gateway information
            $table->string('transaction_id', 150)->nullable();
            $table->string('gateway', 100)->nullable();
            $table->string('gateway_reference', 150)->nullable();
            $table->text('gateway_response')->nullable();
            // Payment lifecycle
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('status_id');
            $table->index('payment_method_id');
            $table->index('transaction_id');
            $table->index('gateway_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
