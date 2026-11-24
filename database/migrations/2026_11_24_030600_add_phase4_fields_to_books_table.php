<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 spec §12.2 — books extension (new columns only; no existing
 * column is changed or removed). `licensed_pdf_price_bdt` is unused in
 * Phase 4 (spec A6 — licensed PDF is included with a bought book).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('is_preorder')->default(false);
            $table->timestamp('preorder_release_at')->nullable();
            $table->unsignedInteger('licensed_pdf_price_bdt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['is_preorder', 'preorder_release_at', 'licensed_pdf_price_bdt']);
        });
    }
};
