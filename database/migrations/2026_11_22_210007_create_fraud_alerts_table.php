<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('trx_id_normalized')->index();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('existing_payment_id')->constrained('payments')->cascadeOnDelete();
            $table->enum('status', ['open', 'confirmed_fraud', 'mistake', 'legitimate'])->default('open');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('fraud_alerts'); }
};
