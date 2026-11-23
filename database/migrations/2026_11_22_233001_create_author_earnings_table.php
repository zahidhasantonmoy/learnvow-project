<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 §7.2 — author_earnings: the author's earnings ledger.
 * amount_bdt is SIGNED int: sales/pool are (+), refund reversals are (−).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('author_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained('books')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->char('period', 7);
            $table->enum('type', ['sale', 'pool', 'adjustment'])->default('sale');
            $table->integer('amount_bdt');
            $table->unsignedTinyInteger('share_pct')->default(80);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['author_id', 'period']);
            $table->index(['author_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_earnings');
    }
};
