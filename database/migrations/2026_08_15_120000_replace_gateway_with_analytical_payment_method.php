<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('payment_method', 10)->nullable()->after('customer_note');
        });

        DB::statement("UPDATE appointments SET payment_method = CASE WHEN payments.billing_type = 'PIX' THEN 'pix' WHEN payments.billing_type = 'CREDIT_CARD' THEN 'card' ELSE 'cash' END FROM payments WHERE payments.appointment_id = appointments.id AND appointments.payment_method IS NULL");
        DB::table('appointments')->whereNull('payment_method')->update(['payment_method' => 'cash']);
        DB::table('appointments')->where('status', 'pending_payment')->update(['status' => 'confirmed', 'confirmed_at' => DB::raw('COALESCE(confirmed_at, NOW())')]);

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('payment_method', 10)->nullable(false)->change();
            $table->dropIndex(['status', 'reserved_until']);
            $table->dropColumn('reserved_until');
        });

        DB::statement('ALTER TABLE appointments ALTER COLUMN status SET DEFAULT \'confirmed\'');
        DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_no_overlap");
        DB::statement(<<<'SQL'
            ALTER TABLE appointments
            ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (barber_id WITH =, tstzrange(starts_at, ends_at) WITH &&)
            WHERE (status IN ('confirmed', 'attended'))
        SQL);

        Schema::dropIfExists('payments');
        Schema::dropIfExists('webhook_events');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['asaas_customer_id']);
            $table->dropColumn(['asaas_customer_id', 'document']);
        });
    }

    public function down(): void
    {
        // A transição destrutiva não restaura dados do gateway.
    }
};
