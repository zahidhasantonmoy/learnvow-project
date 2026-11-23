<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 §7.4 — reading_minutes: per-month reading ledger (pool raw material).
 * One row per (user, book, period). minutes only grows; counted is set
 * by PayoutService::closePeriod(); flagged rows never count.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reading_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->char('period', 7);
            $table->unsignedInteger('minutes')->default(0);
            $table->boolean('counted')->default(false);
            $table->boolean('flagged')->default(false);
            $table->string('flag_note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'book_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_minutes');
    }
};
