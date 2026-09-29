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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            // Warehouse belongs to a seller
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['seller_id','code',]);
            $table->index('seller_id');
            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
