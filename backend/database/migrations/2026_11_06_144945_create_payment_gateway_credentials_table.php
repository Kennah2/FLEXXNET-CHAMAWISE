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
        Schema::create('payment_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('daraja');
            $table->string('environment')->default('sandbox');
            // consumer_key/consumer_secret/passkey are encrypted at the application layer
            // (Model::casts 'encrypted') before they ever reach the database — see
            // docs/FINANCIAL_LEDGER.md's "Payments (Phase 4)" section for why each chama holds
            // its own Daraja app credentials rather than the platform holding one shared set.
            $table->text('consumer_key')->nullable();
            $table->text('consumer_secret')->nullable();
            $table->string('shortcode')->nullable();
            $table->text('passkey')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('callback_token', 64)->unique();
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_credentials');
    }
};
