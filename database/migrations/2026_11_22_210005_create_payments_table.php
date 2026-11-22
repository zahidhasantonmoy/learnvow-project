<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['bkash', 'nagad', 'wallet']);
            $table->string('trx_id');
            $table->string('trx_id_normalized')->index();
            $table->unsignedInteger('amount_bdt');
            $table->enum('status', ['awaiting_verification', 'verified', 'rejected', 'duplicate_suspect'])->default('awaiting_verification');
            $table->timestamp('submitted_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('fix_count')->default(0);
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('payments'); }
};
