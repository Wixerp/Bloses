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
        Schema::create('seller_suspension_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('previous_status_id');
            $table->unsignedBigInteger('suspended_status_id');

            $table->text('reason');
            $table->unsignedBigInteger('action_by')->nullable();
            $table->timestamp('suspended_at')->useCurrent();
            $table->timestamp('reactivated_at')->nullable();
            $table->unsignedBigInteger('reactivated_by')->nullable();
            $table->text('reactivation_reason')->nullable();

            $table->timestamps();

            $table->index(['seller_id', 'reactivated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_suspension_histories');
    }
};
