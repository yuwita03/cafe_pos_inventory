<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_email')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_cash_valid');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_cash_valid CHECK (method <> 'cash' OR (status IN ('pending', 'expired', 'failed') AND cash_received_idr IS NULL AND change_idr IS NULL) OR (cash_received_idr IS NOT NULL AND cash_received_idr >= amount_idr AND change_idr IS NOT NULL AND change_idr = cash_received_idr - amount_idr))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (DB::table('payments')->where('method', 'cash')->whereNull('cash_received_idr')->exists()) {
                throw new RuntimeException('Pending cash payments must be resolved before rolling back this migration.');
            }
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_cash_valid');
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_cash_valid CHECK (method <> 'cash' OR (cash_received_idr IS NOT NULL AND cash_received_idr >= amount_idr AND change_idr IS NOT NULL AND change_idr = cash_received_idr - amount_idr))");
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('customer_email');
        });
    }
};
