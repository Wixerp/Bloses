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
        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnUpdate()->cascadeOnDelete();
            // Address type from master_types
            // shipping / billing
            $table->foreignId('type_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Snapshot of customer address
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->text('address_line_1');
            $table->text('address_line_2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100);
            $table->text('delivery_instructions')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_addresses');
    }
};
