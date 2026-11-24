<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vocabulary_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('word');
            $table->text('meaning_en')->nullable();
            $table->text('meaning_bn')->nullable();
            $table->text('example')->nullable();
            $table->foreignId('book_id')->nullable()->constrained('books')->nullOnDelete();
            $table->string('cfi')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'word']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocabulary_words');
    }
};
