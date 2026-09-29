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
        
        Schema::create('seller_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->cascadeOnDelete();
            // Account type from master_types
            // bank / easypaisa / jazzcash etc.
            $table->foreignId('account_type_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->string('account_holder_name', 150);
            $table->string('bank_name', 150)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('iban', 100)->nullable();
            $table->string('branch_name', 150)->nullable();
            $table->string('branch_code', 50)->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index('seller_id');
            $table->index('account_type_id');
            $table->index('iban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_bank_accounts');
    }
};
