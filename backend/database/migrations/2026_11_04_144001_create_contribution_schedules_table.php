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
        Schema::create('contribution_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('frequency');
            $table->string('currency', 3)->default('KES');
            $table->unsignedBigInteger('amount_cents');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('deadline_time', 5)->default('23:59');
            $table->string('timezone')->default('Africa/Nairobi');
            $table->unsignedInteger('grace_period_days')->default(0);
            $table->unsignedBigInteger('penalty_amount_cents')->default(0);
            $table->string('applies_to')->default('all');
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_schedules');
    }
};
