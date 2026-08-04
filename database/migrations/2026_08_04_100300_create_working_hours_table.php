<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // grade semanal recorrente por barbeiro; um dia pode ter mais de uma faixa
        // (ex.: 09:00–12:00 e 13:00–19:00 quando o almoço é fixo)
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');   // 0 = domingo … 6 = sábado
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['barber_id', 'weekday', 'starts_at']);
            $table->index(['barber_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
