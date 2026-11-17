<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // App scaffold still allows SQLite for the mock UI. PIC D needs PostgreSQL.
            return;
        }
        DB::unprepared(file_get_contents(base_path('data-platform/analytics/schema.sql')));
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop owned facts first, then dimensions; do NOT CASCADE into PIC E forecast tables.
            // If E already references dimensions, this rollback correctly refuses the drop.
            foreach ([
                'fact_receivable_aging_daily', 'fact_receivables_daily', 'fact_stock_daily',
                'fact_credit_issuance', 'fact_operating_expenses', 'fact_cash_receipts',
                'fact_sales', 'etl_runs', 'dim_member', 'dim_product', 'dim_date',
            ] as $table) {
                DB::statement('DROP TABLE IF EXISTS analytics.'.$table);
            }
        }
    }
};
