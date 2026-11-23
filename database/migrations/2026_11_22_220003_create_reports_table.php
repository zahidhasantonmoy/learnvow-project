<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builder C — Phase 3 spec §7.8. Reports against a book or a review.
 * reportable_type is the plain table name ('books' / 'reviews'), per spec —
 * not a polymorphic morph type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users');
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->enum('reason', ['copyright', 'spam', 'abuse', 'wrong_content', 'other']);
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'reviewing', 'action_taken', 'dismissed'])->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
