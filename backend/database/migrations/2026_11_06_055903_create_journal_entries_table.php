<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->string('source_type')->nullable(); // e.g. App\Models\Contribution
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('posted_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('posted'); // posted, reversed
            $table->foreignId('reverses_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'posted_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
