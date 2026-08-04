<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->constrained();
            $table->foreignId('service_id')->constrained();

            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->string('status', 20)->default('pending_payment');
            $table->string('origin', 10)->default('online');   // online | manual

            // preço e duração congelados: mexer no catálogo não reescreve o passado
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('duration_min');

            $table->text('customer_note')->nullable();

            $table->timestampTz('reserved_until')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('attended_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->uuid('public_token')->unique();
            $table->timestamps();

            $table->index(['barber_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['status', 'reserved_until']);
            $table->index(['customer_id', 'starts_at']);
        });

        // Exclusividade do slot garantida pelo banco: duas transações concorrentes
        // no mesmo horário do mesmo barbeiro — a segunda estoura e vira 409.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (
                barber_id WITH =,
                tstzrange(starts_at, ends_at) WITH &&
            )
            WHERE (status IN ('pending_payment', 'confirmed', 'attended'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
