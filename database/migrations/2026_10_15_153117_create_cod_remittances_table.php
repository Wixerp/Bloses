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
        if (!Schema::hasTable('cod_remittances')) {
            Schema::create('cod_remittances', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->foreignId('shipment_id')
                    ->unique()
                    ->constrained('shipments');

                $table->foreignId('order_id')
                    ->constrained('orders');

                $table->foreignId('seller_id')
                    ->constrained('sellers');

                $table->foreignId('status_id')
                    ->constrained('master_types');

                $table->decimal('expected_amount', 12, 2)->default(0);
                $table->decimal('collected_amount', 12, 2)->default(0);
                $table->decimal('remitted_amount', 12, 2)->default(0);

                $table->string('currency', 10)->default('PKR');

                $table->string('courier_reference', 150)->nullable();
                $table->string('received_reference', 150)->nullable();

                $table->timestamp('collected_at')->nullable();
                $table->timestamp('remitted_at')->nullable();

                $table->text('notes')->nullable();

                $table->timestamps();

                $table->index('order_id');
                $table->index('seller_id');
                $table->index('status_id');
            });

            DB::statement("
                ALTER TABLE cod_remittances
                ADD CONSTRAINT cod_remittances_amounts_nonnegative
                CHECK (
                    expected_amount >= 0
                    AND collected_amount >= 0
                    AND remitted_amount >= 0
                )
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cod_remittances');
    }
};
