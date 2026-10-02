<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            // Signed quantity: positive for receipts, negative for sales.
            // Current stock = sum(quantity) per ingredient (ledger, not a
            // mutable balance column).
            $table->decimal('quantity', 18, 3);
            $table->string('type', 10); // 'receipt' | 'sale'

            // Exactly one of these two is set — the unambiguous source of this
            // movement. Nullable FKs instead of a generic polymorphic
            // reference, so referential integrity is real, not conventional.
            $table->foreignId('delivery_line_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index('ingredient_id');
            // At most one sale movement per ingredient per sale (a recipe lists
            // each ingredient once, so this also guards against a bug double
            // applying the same recipe line).
            $table->unique(['sale_id', 'ingredient_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_type_check
                CHECK (type IN ('receipt', 'sale'))
        SQL);

        // Exactly one source: receipts reference a delivery_line, sales
        // reference a sale, never both, never neither.
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_single_source_check
                CHECK (
                    (type = 'receipt' AND delivery_line_id IS NOT NULL AND sale_id IS NULL)
                    OR
                    (type = 'sale' AND sale_id IS NOT NULL AND delivery_line_id IS NULL)
                )
        SQL);

        // Sign matches movement type: receipts increase stock, sales decrease
        // it. Sale quantity may legitimately be zero-crossing on the running
        // balance, but the movement itself always carries a strictly negative
        // deduction, and receipts are always strictly positive.
        DB::statement(<<<'SQL'
            ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_sign_check
                CHECK (
                    (type = 'receipt' AND quantity > 0)
                    OR
                    (type = 'sale' AND quantity < 0)
                )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
