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
        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('seller_bank_account_id')->nullable()->constrained('seller_bank_accounts')->nullOnDelete();
            // Payout status from master_types
            // pending / processing / paid / failed / cancelled
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Amount before deductions
            $table->decimal('gross_amount', 15, 2)->default(0);
            // Platform commission
            $table->decimal('commission_amount', 15, 2)->default(0);
            // Other deductions
            $table->decimal('deduction_amount', 15, 2)->default(0);
            // Final amount transferred to seller
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('PKR');
            // Marketplace payout reference
            $table->string('payout_number', 100)->unique();
            // Bank/payment transaction reference
            $table->string('transaction_id', 150)->nullable();
            $table->string('payment_gateway', 100)->nullable();
            $table->text('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index('seller_id');
            $table->index('seller_bank_account_id');
            $table->index('status_id');
            $table->index('transaction_id');
            $table->index('processed_at');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_payouts');
    }
};
