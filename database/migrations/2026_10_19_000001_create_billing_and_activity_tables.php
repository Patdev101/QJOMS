<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // one invoice per job order; its status (unpaid / partial / paid) comes from the payments
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->foreignId('job_order_id')->unique()->constrained();
            $table->date('date');
            $table->date('due_date');
            $table->decimal('amount', 14, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 14, 2);
            $table->string('method', 30); // cash | check | bank | gcash
            $table->string('reference', 100)->nullable();
            $table->timestamps();
        });

        // what happened to a quotation, job order or invoice, and when
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 30); // quotation | job_order | invoice
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('quotation_id')->nullable()->index(); // groups the whole story of one quotation
            $table->string('action', 30);
            $table->string('description');
            $table->string('user_name')->nullable(); // filled in once login exists
            $table->timestamp('created_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
    }
};
