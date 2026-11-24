<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 4 spec §12.1.4 — manual print / POD orders. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('book_id')->constrained('books');
            $table->foreignId('author_id')->constrained('authors');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_bdt');
            $table->unsignedInteger('delivery_charge_bdt')->default(0);
            $table->unsignedInteger('total_bdt');
            // Partner cost is undecided (spec A4) — Admin writes it per order.
            $table->unsignedInteger('partner_cost_bdt')->nullable();
            $table->string('partner_payment_trx_id')->nullable();
            $table->enum('status', ['ordered', 'confirmed', 'sent_to_partner', 'printing', 'shipped', 'delivered', 'cancelled'])->default('ordered');
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            // DEVIATION from spec §2.1 (documented): §2.1 assumes the TrxID
            // lives on a `payments` row, but Phase 2 `payments.order_id` is
            // NOT NULL and `orders.type` has no print value — no Payment row
            // can exist for a print order without changing Phase 2 tables
            // (forbidden). So the buyer's manual TrxID is stored here until
            // the coordinator decides (nullable payments.order_id, or a
            // print order type). `payment_id` stays for that future link.
            $table->string('payment_method')->nullable();
            $table->string('payment_trx_id')->nullable();
            $table->string('delivery_name');
            $table->string('delivery_phone');
            $table->text('delivery_address');
            $table->string('district')->nullable();
            $table->foreignId('partner_id')->nullable()->constrained('users');
            $table->string('tracking_no')->nullable();
            $table->text('admin_note')->nullable();
            $table->boolean('ordered_by_author')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_orders');
    }
};
