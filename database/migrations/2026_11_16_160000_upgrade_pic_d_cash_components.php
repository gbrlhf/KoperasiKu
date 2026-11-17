<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(file_get_contents(base_path('data-platform/analytics/schema.sql')));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE analytics.fact_cash_receipts DROP COLUMN IF EXISTS advance_amount, DROP COLUMN IF EXISTS sale_amount, DROP COLUMN IF EXISTS receivable_amount');
        }
    }
};
