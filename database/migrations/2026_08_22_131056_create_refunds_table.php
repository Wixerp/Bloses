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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            // Refund status from master_types
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Refund reason from master_types
            $table->foreignId('reason_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->decimal('requested_amount', 15, 2)->default(0);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('PKR');
            $table->string('refund_number', 100)->unique();
            // Gateway refund reference
            $table->string('transaction_id', 150)->nullable();
            $table->string('gateway', 100)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('gateway_response')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('order_item_id');
            $table->index('payment_id');
            $table->index('user_id');
            $table->index('status_id');
            $table->index('reason_id');
            $table->index('transaction_id');
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
