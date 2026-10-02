<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX menu_items_name_lower_unique ON menu_items (lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
