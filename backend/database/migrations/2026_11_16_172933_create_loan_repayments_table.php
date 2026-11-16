<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            // How this payment was actually allocated across principal vs interest once FIFO'd
            // against outstanding installments — see LoanRepaymentAllocator. Denormalized here
            // (rather than requiring a join through loan_installment_allocations) since a
            // repayment's principal/interest split is also what the ledger entry posts.
            $table->unsignedBigInteger('principal_cents')->default(0);
            $table->unsignedBigInteger('interest_cents')->default(0);
            $table->dateTime('paid_at');
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['loan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
