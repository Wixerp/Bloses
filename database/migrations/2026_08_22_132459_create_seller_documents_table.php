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
        Schema::create('seller_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnUpdate()->cascadeOnDelete();
            // Document type from master_types
            $table->foreignId('document_type_id')->nullable()->constrained('master_types')->nullOnDelete();
            // Document verification status
            $table->foreignId('status_id')->nullable()->constrained('master_types')->nullOnDelete();
            $table->string('document_number', 150)->nullable();
            $table->string('file_path', 500);
            $table->string('original_file_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('seller_id');
            $table->index('document_type_id');
            $table->index('status_id');
            $table->index('document_number');
            $table->index('verified_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_documents');
    }
};
