<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // No automatic deletion or guessing: reconcile legacy duplicates before deployment.
        if (DB::table('credit_policies')->where('active', true)->count() > 1) {
            throw new RuntimeException('Multiple active credit policies: reconcile before migration.');
        }
        if (DB::table('receivable_payment_allocations')->select('payment_id', 'receivable_id')
            ->groupBy('payment_id', 'receivable_id')->havingRaw('COUNT(*) > 1')->first()) {
            throw new RuntimeException('Duplicate allocations: reconcile before migration.');
        }
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->string('status', 20)->default('ACTIVE');
            $table->index(['product_id', 'status'], 'pic_b_reservation_product_status_idx');
        });
        // Only classify existing rows; historical deleted reservations cannot be reconstructed safely.
        DB::table('stock_reservations')->whereIn('order_id', DB::table('orders')->where('status', 'COMPLETED')->select('id'))->update(['status' => 'FULFILLED']);
        DB::table('stock_reservations')->whereIn('order_id', DB::table('orders')->where('status', 'CANCELLED')->select('id'))->update(['status' => 'CANCELLED']);
        Schema::table('receivable_payment_allocations', function (Blueprint $table): void {
            $table->unique(['payment_id', 'receivable_id'], 'pic_b_allocation_pair_unique');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX pic_b_single_active_credit_policy ON credit_policies (active) WHERE active = true');
            DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT pic_b_reservation_valid CHECK (quantity > 0 AND status IN ('ACTIVE','FULFILLED','CANCELLED'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_reservations DROP CONSTRAINT IF EXISTS pic_b_reservation_valid');
            DB::statement('DROP INDEX IF EXISTS pic_b_single_active_credit_policy');
        }
        Schema::table('receivable_payment_allocations', function (Blueprint $table): void {
            $table->dropUnique('pic_b_allocation_pair_unique');
        });
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropIndex('pic_b_reservation_product_status_idx');
            $table->dropColumn('status');
        });
    }
};
