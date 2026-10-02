<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            // Idempotency-Key request header, scoped to the order (brief
            // section 6: "Idempotent deliveries").
            $table->uuid('idempotency_key');
            // SHA-256 of the canonicalized request payload, used to detect a
            // same-key-different-payload conflict without re-deriving it from
            // the (mutable-looking) delivery_lines rows every time.
            $table->string('request_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['purchase_order_id', 'idempotency_key']);
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
