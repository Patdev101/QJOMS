<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // tariffs are grouped like the supplier's sheet, e.g. "Hull, Hold & Tank Preservation" › "Gritblasting"
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('type');
            $table->string('subcategory', 100)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn(['category', 'subcategory']);
        });
    }
};
