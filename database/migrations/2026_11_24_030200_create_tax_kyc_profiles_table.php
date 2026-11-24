<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 4 spec §12.1.14 — author tax / KYC profile (one per author). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_kyc_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->unique()->constrained('authors');
            $table->string('legal_name');
            $table->string('nid_no')->nullable();
            $table->string('tin_no')->nullable();
            $table->text('address')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_kyc_profiles');
    }
};
