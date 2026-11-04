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
        Schema::create('contribution_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribution_id')->constrained()->restrictOnDelete();
            $table->foreignId('contribution_obligation_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->dateTime('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['contribution_obligation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribution_allocations');
    }
};
