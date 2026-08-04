<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // external_id único = idempotência: o Asaas reenvia o mesmo evento até receber 200
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->default('asaas');
            $table->string('external_id')->unique();
            $table->string('event', 60);
            $table->jsonb('payload');
            $table->timestampTz('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
