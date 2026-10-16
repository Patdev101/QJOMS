<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_no', 50)->nullable();
            // default discount (%) filled in on every new quotation for this customer
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        // service tariff and item tariff live in one table, told apart by type
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // item | service
            $table->string('code', 50)->unique();
            $table->string('description');
            $table->string('uom', 20);
            $table->string('unit', 50)->nullable(); // name of the unit
            $table->decimal('cost', 12, 2)->default(0); // internal, never shown to the customer
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no', 20)->unique();
            $table->date('date');
            $table->foreignId('customer_id')->constrained();
            // copied from the customer so old quotations keep what was quoted
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_no', 50)->nullable();
            $table->string('project')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending | approved
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);
            $table->string('code', 50);
            $table->string('description');
            $table->string('uom', 20);
            $table->decimal('qty', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();
        });

        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->string('jo_no', 20)->unique();
            $table->date('date');
            $table->foreignId('quotation_id')->unique()->constrained();
            $table->string('status', 20)->default('open'); // open | ongoing | completed | billed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('tariffs');
        Schema::dropIfExists('customers');
    }
};
