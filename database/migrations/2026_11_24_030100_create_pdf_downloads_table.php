<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 4 spec §12.1.5 — licensed PDF download log (separate from `downloads`). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('book_id')->constrained('books');
            $table->foreignId('file_id')->nullable()->constrained('book_files');
            $table->string('watermark_text');
            $table->string('ip_address')->nullable();
            $table->timestamp('downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_downloads');
    }
};
