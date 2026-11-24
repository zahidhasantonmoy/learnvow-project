<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 (spec §2.4, deviation D2): book_files.type needs 'pdf'
     * so a licensed-PDF file row can exist. Additive only — the old
     * values are untouched. Spec §12.2 did not list book_files, but
     * §2.4 requires BookFileService::fileFor($book, 'pdf') to work,
     * which is impossible while the enum lacks 'pdf'.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `book_files` MODIFY COLUMN `type` ENUM('epub','audio','cover','pdf') NOT NULL");

            return;
        }

        // SQLite has no ALTER ENUM — rebuild the table with the new CHECK.
        Schema::disableForeignKeyConstraints();

        Schema::create('book_files_new', function ($table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['epub', 'audio', 'cover', 'pdf']);
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime')->nullable();
            $table->timestamps();
            $table->unique(['book_id', 'type']);
        });

        DB::statement('INSERT INTO book_files_new (id, book_id, type, disk, path, size_bytes, mime, created_at, updated_at) SELECT id, book_id, type, disk, path, size_bytes, mime, created_at, updated_at FROM book_files');
        Schema::drop('book_files');
        Schema::rename('book_files_new', 'book_files');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `book_files` MODIFY COLUMN `type` ENUM('epub','audio','cover') NOT NULL");

            return;
        }

        Schema::disableForeignKeyConstraints();

        // 'pdf' rows cannot exist under the old enum.
        DB::table('book_files')->where('type', 'pdf')->delete();

        Schema::create('book_files_old', function ($table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['epub', 'audio', 'cover']);
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime')->nullable();
            $table->timestamps();
            $table->unique(['book_id', 'type']);
        });

        DB::statement('INSERT INTO book_files_old (id, book_id, type, disk, path, size_bytes, mime, created_at, updated_at) SELECT id, book_id, type, disk, path, size_bytes, mime, created_at, updated_at FROM book_files');
        Schema::drop('book_files');
        Schema::rename('book_files_old', 'book_files');

        Schema::enableForeignKeyConstraints();
    }
};
