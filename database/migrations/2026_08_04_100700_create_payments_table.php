<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('asaas');
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('billing_type', 20);          // PIX | CREDIT_CARD
            $table->unsignedInteger('amount_cents');
            $table->string('status', 30)->default('pending');
            $table->text('invoice_url')->nullable();
            $table->text('pix_payload')->nullable();
            $table->text('pix_qr_base64')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->unsignedInteger('refund_amount_cents')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
