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
        Schema::create('contribution_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->dateTime('due_at');
            $table->dateTime('grace_expires_at');
            $table->unsignedBigInteger('amount_due_cents');
            $table->unsignedBigInteger('amount_paid_cents')->default(0);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('penalty_amount_cents')->default(0);
            $table->string('penalty_status')->default('none');
            $table->foreignId('penalty_waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('penalty_waived_reason')->nullable();
            $table->timestamps();

            $table->unique(['contribution_schedule_id', 'user_id', 'period_start']);
            $table->index(['organization_id', 'user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_obligations');
    }
};
