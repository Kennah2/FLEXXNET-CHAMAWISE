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
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('contribution_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('KES');
            $table->string('payment_method');
            $table->string('transaction_reference')->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending_verification');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Not a unique constraint: a reference may legitimately be re-recorded (e.g. after
            // the first entry was rejected for an unrelated reason). The rule that actually
            // matters — the same reference must never be verified twice — is enforced at
            // verification time (ContributionController::verify), where it can be checked
            // against status rather than blocking storage of every row outright.
            $table->index(['organization_id', 'transaction_reference']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
