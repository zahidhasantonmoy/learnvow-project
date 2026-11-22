<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['book', 'premium', 'gift', 'wallet_topup']);
            $table->enum('status', ['pending_payment', 'awaiting_verification', 'paid', 'failed', 'duplicate_suspect', 'refunded', 'cancelled'])->default('pending_payment');
            $table->unsignedInteger('subtotal_bdt');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('coupon_amt_bdt')->default(0);
            $table->unsignedInteger('wallet_used_bdt')->default(0);
            $table->unsignedInteger('total_bdt');
            $table->timestamp('refund_requested_at')->nullable();
            $table->enum('refund_status', ['none', 'requested', 'under_review', 'approved', 'rejected'])->default('none');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
