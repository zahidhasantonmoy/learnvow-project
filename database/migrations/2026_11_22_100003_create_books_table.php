<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('title_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('description_en')->nullable();
            $table->enum('language', ['bn', 'en']);
            $table->unsignedInteger('price_bdt');
            $table->unsignedInteger('list_price_bdt')->nullable();
            $table->string('isbn')->nullable()->unique();
            $table->unsignedSmallInteger('pages')->nullable();
            $table->boolean('has_audiobook')->default(false);
            $table->boolean('is_free')->default(false);
            $table->boolean('is_public_domain')->default(false);
            $table->boolean('is_subscription_included')->default(true);
            $table->unsignedTinyInteger('sample_percent')->default(10);
            $table->enum('status', ['draft', 'in_review', 'published', 'rejected', 'taken_down'])->default('draft');
            $table->char('cover_c1', 7)->nullable();
            $table->char('cover_c2', 7)->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('file_path')->nullable();
            $table->json('rights_territories')->nullable();
            $table->decimal('rating_avg', 2, 1)->default(0);
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('readers_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
