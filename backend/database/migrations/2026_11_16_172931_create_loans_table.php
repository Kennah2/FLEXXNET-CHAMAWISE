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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('requested_amount_cents');
            $table->unsignedInteger('requested_term_months');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');

            // Null until approved — the approving official sets the actual terms, which may
            // differ from what was requested (a real chama committee routinely adjusts amount/
            // term rather than only ever granting exactly what was asked for).
            $table->unsignedBigInteger('approved_amount_cents')->nullable();
            $table->unsignedInteger('term_months')->nullable();
            // Basis points (1/100 of a percent) — an integer, never a float, for the same reason
            // every other money-adjacent figure in this app is an integer (see docs/FINANCIAL_LEDGER.md).
            $table->unsignedInteger('interest_rate_bps')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disbursed_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
