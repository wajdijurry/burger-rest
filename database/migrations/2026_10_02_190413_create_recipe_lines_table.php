<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            // NUMERIC(18,3): exact decimal, 3 fractional digits, matches the
            // Quantity value object's canonical precision.
            $table->decimal('quantity', 18, 3);
            $table->timestamps();

            // Each ingredient may appear only once per recipe (reject duplicates
            // rather than silently merging quantities).
            $table->unique(['menu_item_id', 'ingredient_id']);
        });

        // Recipe quantities must be strictly positive.
        DB::statement(
            'ALTER TABLE recipe_lines ADD CONSTRAINT recipe_lines_quantity_positive CHECK (quantity > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_lines');
    }
};
