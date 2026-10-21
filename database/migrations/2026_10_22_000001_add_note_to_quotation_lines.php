<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // an optional note on a quotation line, printed under its description ("Lay days: July 02 – July 31")
    public function up(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
