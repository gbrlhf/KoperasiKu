<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MigrationSafetyPgsqlTest extends TestCase
{
    public function test_forward_only_migration_passes_on_empty_and_legacy_populated_database(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        // 1. Pastikan migrasi terbaru sudah terpasang pada schema kosong
        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('members'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasTable('credit_policy_logs'));
        $this->assertTrue(Schema::hasColumn('orders', 'idempotency_key'));
        $this->assertTrue(Schema::hasColumn('orders', 'pickup_idempotency_key'));
        $this->assertTrue(Schema::hasColumn('payments', 'external_reference'));
        $this->assertTrue(Schema::hasColumn('payments', 'verified_at'));
        $this->assertTrue(Schema::hasColumn('payments', 'pending_allocations'));

        // 2. Uji skenario database berisi data lama sebelum migrasi 2026_11_18_000001
        $suffix = strtolower(Str::random(6));
        $userId = '00000000-0000-0000-0000-000000000001';
        $catId = (string) Str::uuid();
        $prodId = (string) Str::uuid();
        $orderId = (string) Str::uuid();
        $actualCatId = $catId;
        $actualProdId = $prodId;

        try {
            foreach (array_reverse([
                'database/migrations/2026_11_18_000001_harden_core_constraints_and_audit_tables.php',
                'database/migrations/2026_11_18_000002_create_idempotency_keys_table.php',
                'database/migrations/2026_11_18_000003_prepare_hpp_immutability_and_pic_c_schema.php',
            ]) as $migrationPath) {
                Artisan::call('migrate:rollback', ['--path' => $migrationPath, '--force' => true]);
            }
            DB::statement('DROP TABLE IF EXISTS members CASCADE');

            DB::table('users')->updateOrInsert(['id' => $userId], [
                'email' => "ahmad.legacy.{$suffix}@polman-bandung.ac.id",
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('categories')->insert([
                'id' => $catId,
                'name' => "Sembako Legacy {$suffix}",
                'slug' => "sembako-legacy-{$suffix}",
                'icon' => 'bi-box',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->insert([
                'id' => $prodId,
                'category_id' => $actualCatId,
                'sku' => "LEG-{$suffix}",
                'name' => 'Beras Legacy 5kg',
                'selling_price' => 70000,
                'current_cost' => 60000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventory_balances')->updateOrInsert(['product_id' => $actualProdId], [
                'on_hand' => 25,
                'reserved' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('credit_policies')->updateOrInsert(['id' => '00000000-0000-0000-0009-000000000001'], [
                'limit_amount' => 1000000,
                'due_days' => 7,
                'active' => true,
                'effective_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('member_credit_accounts')->updateOrInsert(['member_id' => $userId], [
                'status' => 'ACTIVE',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('orders')->insert([
                'id' => $orderId,
                'member_id' => $userId,
                'channel' => 'ONLINE_PICKUP',
                'status' => 'COMPLETED',
                'payment_choice' => 'CREDIT',
                'order_no' => "ORD-LEG-{$suffix}",
                'pickup_code' => 'PKP-' . random_int(90000, 99999),
                'total_amount' => 70000,
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('order_items')->insert([
                'id' => (string) Str::uuid(),
                'order_id' => $orderId,
                'product_id' => $actualProdId,
                'quantity' => 1,
                'unit_price_snapshot' => 70000,
                'hpp_snapshot' => 60000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('receivables')->insert([
                'id' => (string) Str::uuid(),
                'order_id' => $orderId,
                'member_id' => $userId,
                'original_amount' => 70000,
                'outstanding_amount' => 70000,
                'due_date' => now('Asia/Jakarta')->addDays(7)->toDateString(),
                'status' => 'OPEN',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Jalankan migrasi forward-only pada DB berisi data lama
            Artisan::call('migrate', ['--force' => true]);

            // Pastikan member otomatis ter-backfill dan tidak ada data order yang hilang
            $this->assertDatabaseHas('members', [
                'id' => $userId,
                'user_id' => $userId,
                'employee_number' => 'EMP-001',
            ]);
            $this->assertDatabaseHas('orders', ['id' => $orderId, 'order_no' => "ORD-LEG-{$suffix}"]);

            // Pastikan tidak ada ON DELETE CASCADE pada tabel finansial (confdeltype = 'r' -> RESTRICT)
            $cascadeFks = DB::select("
                SELECT conname, confdeltype
                FROM pg_constraint
                WHERE conname IN (
                    'order_items_order_id_foreign',
                    'receivables_order_id_foreign',
                    'receivable_payment_allocations_payment_id_foreign',
                    'receivable_payment_allocations_receivable_id_foreign'
                )
            ");
            $this->assertCount(4, $cascadeFks);
            foreach ($cascadeFks as $fk) {
                $this->assertSame('r', $fk->confdeltype, "FK {$fk->conname} wajib ON DELETE RESTRICT.");
            }

            // Pastikan CHECK constraints aktif menolak stok negatif
            $thrown = false;
            try {
                DB::table('inventory_balances')->where('product_id', $actualProdId)->update(['on_hand' => 1, 'reserved' => 5]);
            } catch (\Illuminate\Database\QueryException $e) {
                $thrown = true;
            }
            $this->assertTrue($thrown, 'CHECK constraint chk_inventory_balances_invariant wajib menolak on_hand < reserved.');
        } finally {
            Artisan::call('migrate', ['--force' => true]);
            DB::table('receivables')->where('order_id', $orderId)->delete();
            DB::table('order_items')->where('order_id', $orderId)->delete();
            DB::table('orders')->where('id', $orderId)->delete();
            DB::table('inventory_balances')->where('product_id', $actualProdId)->delete();
            DB::table('products')->where('id', $actualProdId)->delete();
            DB::table('categories')->where('id', $actualCatId)->delete();
        }
    }
}
