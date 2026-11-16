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
        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->unsignedBigInteger('principal_due_cents');
            $table->unsignedBigInteger('interest_due_cents');
            $table->unsignedBigInteger('principal_paid_cents')->default(0);
            $table->unsignedBigInteger('interest_paid_cents')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_installments');
    }
};
