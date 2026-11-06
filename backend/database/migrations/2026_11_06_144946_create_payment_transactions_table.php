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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('contribution_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contribution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('KES');
            $table->string('provider')->default('daraja');
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable();
            $table->string('status')->default('initiated');
            $table->integer('result_code')->nullable();
            $table->string('result_desc')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->json('raw_callback')->nullable();
            $table->timestamps();

            // Not globally unique: Safaricom scopes CheckoutRequestID to their own system, but
            // this app only ever looks one up scoped to the organization that initiated it (see
            // DarajaCallbackHandler), so organization_id + checkout_request_id is the meaningful
            // uniqueness boundary.
            $table->unique(['organization_id', 'checkout_request_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
