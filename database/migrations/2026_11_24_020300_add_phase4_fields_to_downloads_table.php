<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('downloads', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('size_bytes');
            $table->timestamp('expires_at')->nullable()->after('is_encrypted');
            $table->string('encryption_key_id')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('downloads', function (Blueprint $table) {
            $table->dropColumn(['is_encrypted', 'expires_at', 'encryption_key_id']);
        });
    }
};
