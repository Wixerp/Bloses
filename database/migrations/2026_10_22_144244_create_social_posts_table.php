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
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('provider_post_id', 191);
            $table->string('post_type', 30)->nullable();
            $table->text('caption')->nullable();
            $table->text('media_url')->nullable();
            $table->text('permalink')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('sync_status', 30)->default('imported');
            $table->timestamps();
            $table->unique(['social_account_id', 'provider_post_id'], 'social_posts_account_provider_post_unique');
            $table->index(['product_id', 'sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
