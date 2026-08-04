<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cliente não tem login: a identidade é o telefone normalizado em E.164
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone_e164', 20)->unique();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->string('asaas_customer_id')->nullable()->index();
            $table->timestampTz('first_seen_at')->nullable();
            $table->timestampTz('last_visit_at')->nullable();
            $table->timestamps();

            $table->index('last_visit_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
