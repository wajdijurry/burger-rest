<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->timestamps();
        });

        // Same duplicate-name policy as ingredients: identity is the ID, but
        // exact (case-insensitive) duplicates are rejected as data-entry errors.
        DB::statement('CREATE UNIQUE INDEX suppliers_name_lower_unique ON suppliers (lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
