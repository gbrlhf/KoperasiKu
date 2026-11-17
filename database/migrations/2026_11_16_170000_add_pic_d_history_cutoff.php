<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE analytics.fact_sales ADD COLUMN IF NOT EXISTS before_stock_cutoff boolean');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE analytics.fact_sales DROP COLUMN IF EXISTS before_stock_cutoff');
        }
    }
};
