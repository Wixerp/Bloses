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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Previous status
            $table->foreignId('previous_status_id')->nullable()->constrained('master_types')->nullOnDelete();
            // User/admin/system who changed the status
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index('order_id');
            $table->index('status_id');
            $table->index('previous_status_id');
            $table->index('changed_by');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
