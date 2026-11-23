<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 extension (spec §7.16) — coordinator-created so Builders A/B can
 * use the columns before Builder C's tables land. New columns only; no
 * existing column is changed or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false);
            $table->timestamp('hidden_at')->nullable();
            $table->boolean('allow_pod')->default(true);
            $table->boolean('allow_licensed_pdf')->default(false);
            $table->unsignedInteger('print_price_bdt')->nullable();
            $table->boolean('is_featured')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'is_paused', 'hidden_at', 'allow_pod',
                'allow_licensed_pdf', 'print_price_bdt', 'is_featured',
            ]);
        });
    }
};
