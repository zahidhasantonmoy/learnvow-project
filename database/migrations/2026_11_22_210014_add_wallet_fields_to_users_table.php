<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('wallet_balance_bdt')->default(0);
            $table->integer('points_balance')->default(0);
            $table->timestamp('trial_used_at')->nullable();
            $table->string('referral_code')->unique()->nullable();
            $table->enum('reader_default_font', ['serif', 'sans'])->nullable();
            $table->unsignedTinyInteger('reader_default_size')->nullable();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance_bdt', 'points_balance', 'trial_used_at', 'referral_code', 'reader_default_font', 'reader_default_size']);
        });
    }
};
