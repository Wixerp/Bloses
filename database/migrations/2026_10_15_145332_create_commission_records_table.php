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
        Schema::create('commission_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')->cascadeOnDelete();

            $table->foreignId('seller_id')
                ->constrained('sellers')->cascadeOnDelete();

            $table->foreignId('commission_setting_id')
                ->nullable()
                ->constrained('commission_settings')
                ->nullOnDelete();

            $table->decimal('sale_amount', 12, 2);
            $table->string('commission_type')->default('percentage');
            $table->decimal('commission_rate', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('seller_amount', 12, 2);

            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['order_id', 'seller_id']);
            $table->index(['seller_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_records');
    }
};
