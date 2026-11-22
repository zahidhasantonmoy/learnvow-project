<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->enum('font_family', ['serif', 'sans'])->default('serif');
            $table->unsignedTinyInteger('font_size')->default(100);
            $table->decimal('line_height', 3, 2)->default('1.60');
            $table->enum('theme', ['paper', 'sepia', 'night'])->default('paper');
            $table->unsignedTinyInteger('brightness')->default(100);
            $table->enum('reading_mode', ['paged', 'scroll'])->default('paged');
            $table->timestamps();
            $table->unique(['user_id', 'book_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_settings');
    }
};
