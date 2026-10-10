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
        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('support_ticket_id');
            $table->unsignedBigInteger('user_id');

            // admin ya seller
            $table->string('sender_type', 20);

            $table->text('message');
            $table->string('image')->nullable();

            $table->timestamps();

            $table->foreign('support_ticket_id')
                ->references('id')
                ->on('support_tickets')
                ->cascadeOnDelete();

            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
    }
};
