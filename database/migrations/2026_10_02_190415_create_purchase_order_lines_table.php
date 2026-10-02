<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 18, 3);
            $table->timestamps();

            // Reject duplicate ingredient lines within one order.
            $table->unique(['purchase_order_id', 'ingredient_id']);
        });

        DB::statement(
            'ALTER TABLE purchase_order_lines ADD CONSTRAINT purchase_order_lines_quantity_positive CHECK (quantity_ordered > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
