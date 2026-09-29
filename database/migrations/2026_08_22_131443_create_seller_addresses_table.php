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
        Schema::create('seller_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('area', 150)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('Pakistan');
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->timestamps();
            $table->index('seller_id');
            $table->index('state_id');
            $table->index('city_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_addresses');
    }
};
