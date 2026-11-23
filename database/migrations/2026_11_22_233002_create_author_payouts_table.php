<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 §7.3 — author_payouts: monthly payout per author.
 * Unique(author_id, period). Only created when the total is >= ৳500
 * (pool.min_payout_bdt); below that the money stays accrued.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('author_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->char('period', 7);
            $table->unsignedInteger('sales_bdt')->default(0);
            $table->unsignedInteger('pool_bdt')->default(0);
            $table->unsignedInteger('total_bdt');
            $table->enum('method', ['bkash', 'bank'])->default('bkash');
            $table->string('destination')->nullable();
            $table->enum('status', ['pending', 'approved', 'paid', 'failed'])->default('pending');
            $table->string('payout_trx_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->unique(['author_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_payouts');
    }
};
