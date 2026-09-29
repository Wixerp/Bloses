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
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 100)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            // Return status from master_types
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Return reason from master_types
            $table->foreignId('reason_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->text('customer_note')->nullable();
            $table->text('seller_note')->nullable();
            $table->text('admin_note')->nullable();
            // Pickup / return tracking
            $table->string('carrier', 100)->nullable();
            $table->string('tracking_number', 150)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('user_id');
            $table->index('seller_id');
            $table->index('status_id');
            $table->index('reason_id');
            $table->index('tracking_number');
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
