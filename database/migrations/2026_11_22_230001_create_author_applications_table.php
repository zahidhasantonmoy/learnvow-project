<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (spec §7.1) — Builder A. Author applications: one active
 * application per account (enforced in AuthorService, not a partial
 * unique index — SQLite/MySQL both, spec §7.1 note).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pen_name')->nullable();
            $table->text('bio')->nullable();
            $table->text('bio_en')->nullable();
            $table->string('bkash_number');
            $table->string('id_document_path')->nullable();
            $table->enum('status', ['submitted', 'under_review', 'approved', 'rejected'])->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_applications');
    }
};
