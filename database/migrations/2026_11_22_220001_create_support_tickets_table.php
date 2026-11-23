<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builder C — Phase 3 spec §7.6. Support tickets: reader opens, Support /
 * Admin / Super Admin reply in the same thread (ticket_messages).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->string('subject');
            $table->enum('category', ['payment', 'book', 'technical', 'account', 'other'])->default('other');
            $table->enum('status', ['open', 'in_progress', 'waiting_reply', 'resolved', 'closed', 'escalated'])->default('open');
            $table->enum('priority', ['normal', 'urgent'])->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->enum('escalated_to', ['admin', 'super_admin'])->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
