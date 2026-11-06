<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('withdrawal_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('decision'); // approved, rejected
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['withdrawal_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_approvals');
    }
};
