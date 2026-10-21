<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // shipyard tariff (MSSAI / Tariff 2.0): units in two parts ("Pax / Day" → qty × qty2 × price),
    // longer texts, and the vessel the quotation is for
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->string('uom2', 20)->nullable()->after('uom');
            $table->string('category', 255)->nullable()->change();
            $table->string('subcategory', 500)->nullable()->change();
            $table->string('description', 500)->change();
        });

        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->string('uom2', 20)->nullable()->after('uom');
            $table->decimal('qty2', 12, 2)->nullable()->after('qty');
            $table->string('description', 500)->change();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->string('vessel_name')->nullable()->after('project');
            $table->decimal('loa', 8, 2)->nullable()->after('vessel_name');
            $table->decimal('lbp', 8, 2)->nullable()->after('loa');
            $table->decimal('breadth', 8, 2)->nullable()->after('lbp');
            $table->decimal('depth', 8, 2)->nullable()->after('breadth');
            $table->date('date_arrival')->nullable()->after('depth');
            $table->date('date_drydocked')->nullable()->after('date_arrival');
            $table->date('date_undocked')->nullable()->after('date_drydocked');
            $table->date('date_sail_out')->nullable()->after('date_undocked');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['vessel_name', 'loa', 'lbp', 'breadth', 'depth', 'date_arrival', 'date_drydocked', 'date_undocked', 'date_sail_out']);
        });

        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->dropColumn(['uom2', 'qty2']);
        });

        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn('uom2');
        });
    }
};
