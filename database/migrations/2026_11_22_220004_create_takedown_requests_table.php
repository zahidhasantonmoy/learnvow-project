<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builder C — Phase 3 spec §7.9. Copyright takedown claims.
 * deadline_at = first-response target, claim + 48h (assumption A9: a
 * target, not a hard rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('takedown_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books');
            $table->string('claimant_name');
            $table->string('claimant_email');
            $table->text('reason');
            $table->string('proof_path')->nullable();
            $table->enum('status', ['open', 'awaiting_proof', 'action_taken', 'rejected', 'closed'])->default('open');
            $table->timestamp('deadline_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('takedown_requests');
    }
};
