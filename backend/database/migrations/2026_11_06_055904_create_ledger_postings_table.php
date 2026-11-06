<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            // Only meaningful on postings to a sub-ledgered account (e.g. member_savings) —
            // lets one control account's balance be split per member without a separate ledger
            // account per member.
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('debit_cents')->default(0);
            $table->unsignedBigInteger('credit_cents')->default(0);
            $table->timestamps();

            $table->index(['ledger_account_id', 'user_id']);
            $table->index(['organization_id', 'ledger_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_postings');
    }
};
