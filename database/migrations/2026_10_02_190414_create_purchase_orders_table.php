<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            // draft -> sent -> received -> closed. Kept as a plain string column
            // with a DB check constraint (see PurchaseOrderStatus enum for the
            // single source of truth on transition rules in PHP).
            $table->string('status', 20)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        DB::statement(
            "ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft', 'sent', 'received', 'closed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
