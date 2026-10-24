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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('seller_store_settings')->nullOnDelete();
            $table->string('provider', 30);
            $table->string('provider_account_id', 191);
            $table->string('username')->nullable();
            $table->string('account_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['seller_id', 'provider', 'provider_account_id'],'social_accounts_seller_provider_account_unique');
            $table->index(['seller_id', 'provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
