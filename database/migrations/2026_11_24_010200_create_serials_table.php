<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete();
            $table->string('title');
            $table->string('title_en')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->enum('status', ['ongoing', 'completed', 'paused'])->default('ongoing');
            $table->boolean('is_free')->default(false);
            $table->boolean('is_subscription_included')->default(true);
            $table->unsignedInteger('price_bdt')->nullable();
            // Approval marker — the spec's status enum has no approval
            // state, so AdminService::approveSerial() records it here.
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serials');
    }
};
