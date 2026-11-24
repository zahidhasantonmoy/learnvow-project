<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('serial_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serial_id')->constrained('serials')->cascadeOnDelete();
            $table->unsignedInteger('episode_no');
            $table->string('title');
            $table->text('content');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_free')->default(true);
            $table->unsignedInteger('price_bdt')->nullable();
            $table->timestamps();
            $table->unique(['serial_id', 'episode_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_episodes');
    }
};
