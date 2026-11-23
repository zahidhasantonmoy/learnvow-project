<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 extension (spec §7.16) — coordinator-created. New columns only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->string('payout_method')->default('bkash');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'bank_name', 'bank_account_number']);
        });
    }
};
