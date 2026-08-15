<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // um bloqueio de férias vira uma linha por dia; o period_id costura elas de volta
        Schema::table('time_blocks', function (Blueprint $table) {
            $table->uuid('period_id')->nullable()->after('barber_id');

            $table->index('period_id');
        });
    }

    public function down(): void
    {
        Schema::table('time_blocks', function (Blueprint $table) {
            $table->dropIndex(['period_id']);
            $table->dropColumn('period_id');
        });
    }
};
