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
        Schema::create('seller_store_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('store_name', 150);
            $table->string('store_slug', 180)->unique();
            $table->text('store_description')->nullable();
            $table->string('logo', 500)->nullable();
            $table->string('banner', 500)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('facebook_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('twitter_url', 500)->nullable();
            $table->string('youtube_url', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique('seller_id');
            $table->index('store_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_store_settings');
    }
};
