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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            // percentage / fixed
            $table->foreignId('discount_type_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->decimal('discount_value', 15, 2);
            // Maximum discount for percentage coupons
            $table->decimal('maximum_discount', 15, 2)->nullable();
            // Minimum order amount required
            $table->decimal('minimum_order_amount', 15, 2)->default(0);
            // Total number of times coupon can be used
            $table->unsignedInteger('usage_limit')->nullable();
            // Maximum uses by one customer
            $table->unsignedInteger('user_usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index('discount_type_id');
            $table->index('starts_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
