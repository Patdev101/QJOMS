<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // the main section of the tariff sheet a service belongs to ("B. GENERAL RELATED SERVICES"), for the printout summary
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->string('section', 150)->nullable()->after('type');
        });
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->string('section', 150)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_lines', fn (Blueprint $table) => $table->dropColumn('section'));
        Schema::table('tariffs', fn (Blueprint $table) => $table->dropColumn('section'));
    }
};
