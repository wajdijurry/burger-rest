<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->restrictOnDelete();
            // References a purchase-order *line*, not merely an ingredient, so
            // we can validate it belongs to the target order and compute
            // outstanding quantity per line.
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_received', 18, 3);
            $table->timestamp('created_at')->useCurrent();

            // No duplicate line IDs within one delivery.
            $table->unique(['delivery_id', 'purchase_order_line_id']);
        });

        DB::statement(
            'ALTER TABLE delivery_lines ADD CONSTRAINT delivery_lines_quantity_positive CHECK (quantity_received > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_lines');
    }
};
