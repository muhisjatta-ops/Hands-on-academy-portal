<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three layers, kept strictly apart:
 *
 *   fee_structures  = the PRICE LIST (what a Grade 4 pays this term)
 *   invoices/lines  = the CHARGE     (what this student was actually billed)
 *   payments        = the LEDGER     (what came in, append-only)
 *
 * Raising tuition next term must never change last term's invoice, which is
 * why invoice_lines copy the description and amount instead of pointing at
 * the price list. And no table anywhere stores a "balance" — it is always
 * derived, so it can never silently disagree with the rows beneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();     // Tuition, Transport
            $table->boolean('is_recurring')->default(true);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['fee_category_id', 'grade_level_id', 'term_id'], 'fee_structure_unique');
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_category_id')->nullable()
                ->constrained()->cascadeOnDelete();   // null = applies to all
            $table->string('reason', 120);            // Sibling, Scholarship
            $table->decimal('percent', 5, 2)->default(0);
            $table->decimal('fixed_amount', 12, 2)->default(0);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('granted_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'valid_from']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('status', 12)->default('DRAFT');  // DRAFT|ISSUED|CANCELLED
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One invoice per student per term keeps the ledger unambiguous.
            $table->unique(['student_id', 'term_id']);
            $table->index('status');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 120);       // copied, not referenced
            $table->decimal('amount', 12, 2);         // negative for a discount
            $table->foreignId('fee_structure_id')->nullable()
                ->constrained()->nullOnDelete();      // provenance only
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 12);             // CASH|BANK|MOBILE|CHEQUE
            $table->string('reference', 80)->nullable();
            $table->date('paid_on');
            $table->foreignId('received_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('note', 200)->nullable();

            // A reversal is a new negative row pointing at the original,
            // never an edit or a delete of the original.
            $table->foreignId('reverses_payment_id')->nullable()
                ->constrained('payments')->nullOnDelete();
            $table->string('reversal_reason', 200)->nullable();

            $table->timestamps();

            $table->index(['student_id', 'paid_on']);
        });

        // Splits one payment across invoices: handles part payments and
        // a parent clearing last term's arrears with this term's money.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_categories');
    }
};
