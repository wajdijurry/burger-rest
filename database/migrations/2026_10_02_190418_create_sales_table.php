<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // Stable external idempotency identity for the POS event.
            $table->uuid('event_id')->unique();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            // SHA-256 of the canonicalized request payload (menu_item_id +
            // quantity), used to distinguish a safe retry from a conflicting
            // reuse of the same event_id.
            $table->string('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index('menu_item_id');
        });

        DB::statement(
            'ALTER TABLE sales ADD CONSTRAINT sales_quantity_bounds CHECK (quantity > 0 AND quantity <= 10000)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
