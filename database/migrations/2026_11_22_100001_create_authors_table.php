<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('pen_name')->nullable();
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->text('bio_en')->nullable();
            $table->enum('type', ['author', 'publisher'])->default('author');
            $table->unsignedTinyInteger('revenue_share_pct')->default(80);
            $table->string('bkash_number')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
