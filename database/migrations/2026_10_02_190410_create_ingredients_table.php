<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // Short canonical unit label (g, ml, piece, ...). No unit-conversion
            // table in this scope: every quantity for this ingredient is always
            // expressed in this unit.
            $table->string('unit', 20);
            $table->timestamps();
        });

        // Duplicate-name policy: ingredient identity is the database ID, but we
        // reject case-insensitive duplicate names at creation time to keep the
        // catalog clean (documented assumption in README). Enforced here as a
        // real constraint, not just request validation.
        DB::statement('CREATE UNIQUE INDEX ingredients_name_lower_unique ON ingredients (lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
