<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->date('statement_date');
            $table->unsignedBigInteger('statement_balance_cents');
            // A signed snapshot of what the ledger said the balance was at the moment this
            // reconciliation was recorded — kept even though the ledger itself is append-only,
            // so a later posting backdated before this point doesn't silently rewrite history.
            $table->bigInteger('computed_balance_cents');
            $table->bigInteger('variance_cents');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'financial_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_reconciliations');
    }
};
