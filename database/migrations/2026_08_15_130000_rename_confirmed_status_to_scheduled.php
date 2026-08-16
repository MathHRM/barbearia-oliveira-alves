<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('appointments')->whereIn('status', ['confirmed', 'pending_payment'])->update(['status' => 'scheduled']);

        DB::statement("ALTER TABLE appointments ALTER COLUMN status SET DEFAULT 'scheduled'");
        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_no_overlap');
        DB::statement(<<<'SQL'
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (barber_id WITH =, tstzrange(starts_at, ends_at) WITH &&)
            WHERE (status IN ('scheduled', 'attended'))
        SQL);
    }

    public function down(): void
    {
        DB::table('appointments')->where('status', 'scheduled')->update(['status' => 'confirmed']);

        DB::statement("ALTER TABLE appointments ALTER COLUMN status SET DEFAULT 'confirmed'");
        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_no_overlap');
        DB::statement(<<<'SQL'
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (barber_id WITH =, tstzrange(starts_at, ends_at) WITH &&)
            WHERE (status IN ('confirmed', 'attended'))
        SQL);
    }
};
