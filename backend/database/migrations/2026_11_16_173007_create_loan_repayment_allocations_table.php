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
        Schema::create('loan_repayment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_repayment_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_installment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('principal_cents')->default(0);
            $table->unsignedBigInteger('interest_cents')->default(0);
            // A reversal stamps this rather than deleting the row — the same never-delete rule
            // every other allocation-style table in this app follows (see ContributionAllocation).
            $table->dateTime('reversed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_allocations');
    }
};
