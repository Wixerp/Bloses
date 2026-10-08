<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_earnings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->cascadeOnDelete();

            $table->decimal('gross_amount', 12, 2)->default(0);

            $table->decimal('commission_percentage', 5, 2)
                ->default(10);

            $table->decimal('commission_amount', 12, 2)
                ->default(0);

            $table->decimal('net_amount', 12, 2)
                ->default(0);

            $table->unsignedBigInteger('status_id')->nullable();

            $table->timestamp('available_at')->nullable();

            $table->timestamps();

            $table->foreign('status_id')
                ->references('id')
                ->on('master_types')
                ->nullOnDelete();

            $table->unique(
                ['order_id', 'seller_id'],
                'seller_earnings_order_seller_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_earnings');
    }
};
