<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrencyPgsqlTest extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        if (!self::$migrated) {
            Artisan::call('migrate', ['--force' => true]);
            self::$migrated = true;
        }
    }

    public function test_parallel_stock_checkouts_never_oversell_available_stock(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        $userId1 = (string) Str::uuid();
        $userId2 = (string) Str::uuid();
        $memberId1 = (string) Str::uuid();
        $memberId2 = (string) Str::uuid();
        $catId = (string) Str::uuid();
        $prodId = (string) Str::uuid();
        $sku = 'CSTK-' . strtoupper(Str::random(5));

        try {
            DB::table('roles')->updateOrInsert(['code' => 'MEMBER'], ['description' => 'Member', 'created_at' => now(), 'updated_at' => now()]);
            foreach ([[$userId1, $memberId1, '1'], [$userId2, $memberId2, '2']] as [$uid, $mid, $idx]) {
                DB::table('users')->insert([
                    'id' => $uid,
                    'email' => "conc{$idx}.{$sku}@polman-bandung.ac.id",
                    'password_hash' => bcrypt('password123'),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('user_roles')->insert(['user_id' => $uid, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('members')->insert([
                    'id' => $mid,
                    'user_id' => $uid,
                    'employee_number' => "EMP-{$sku}-{$idx}",
                    'member_number' => "MEM-{$sku}-{$idx}",
                    'full_name' => "Concurrency Member {$idx}",
                    'status' => 'ACTIVE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('categories')->insert([
                'id' => $catId,
                'name' => "Kat {$sku}",
                'slug' => "kat-{$sku}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('products')->insert([
                'id' => $prodId,
                'category_id' => $catId,
                'sku' => $sku,
                'name' => 'Barang Concurrency Stok',
                'selling_price' => 20000,
                'current_cost' => 15000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('inventory_balances')->insert([
                'product_id' => $prodId,
                'on_hand' => 5, // Cukup hanya untuk 1 checkout qty=3
                'reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results = $this->runParallelCheckouts(
                [$memberId1, $prodId, 3, 'CASH', $userId1],
                [$memberId2, $prodId, 3, 'CASH', $userId2]
            );

            $okCount = count(array_filter($results, fn($r) => str_starts_with($r, 'OK:')));
            $errCount = count(array_filter($results, fn($r) => str_starts_with($r, 'ERR:INSUFFICIENT_STOCK:')));

            $this->assertSame(1, $okCount, 'Harus tepat 1 checkout stok lolos: ' . implode(' | ', $results));
            $this->assertSame(1, $errCount, 'Harus tepat 1 checkout stok ditolak INSUFFICIENT_STOCK: ' . implode(' | ', $results));

            $inv = DB::table('inventory_balances')->where('product_id', $prodId)->first();
            $this->assertSame(5, (int) $inv->on_hand);
            $this->assertSame(3, (int) $inv->reserved);
        } finally {
            $orderIds = DB::table('orders')->whereIn('member_id', [$memberId1, $memberId2])->pluck('id');
            if ($orderIds->isNotEmpty()) {
                DB::table('stock_reservations')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
            }
            DB::table('audit_logs')->whereIn('actor_id', [$userId1, $userId2])->delete();
            DB::table('inventory_balances')->where('product_id', $prodId)->delete();
            DB::table('products')->where('id', $prodId)->delete();
            DB::table('categories')->where('id', $catId)->delete();
            DB::table('members')->whereIn('id', [$memberId1, $memberId2])->delete();
            DB::table('user_roles')->whereIn('user_id', [$userId1, $userId2])->delete();
            DB::table('users')->whereIn('id', [$userId1, $userId2])->delete();
        }
    }

    public function test_parallel_credit_checkouts_for_same_member_never_exceed_global_limit(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $catId = (string) Str::uuid();
        $prodId = (string) Str::uuid();
        $policyId = (string) Str::uuid();
        $sku = 'CCRD-' . strtoupper(Str::random(5));

        try {
            DB::table('roles')->updateOrInsert(['code' => 'MEMBER'], ['description' => 'Member', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->insert([
                'id' => $userId,
                'email' => "crdt.{$sku}@polman-bandung.ac.id",
                'password_hash' => bcrypt('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_roles')->insert(['user_id' => $userId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('members')->insert([
                'id' => $memberId,
                'user_id' => $userId,
                'employee_number' => "EMP-{$sku}",
                'member_number' => "MEM-{$sku}",
                'full_name' => 'Concurrency Credit Member',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('member_credit_accounts')->insert([
                'member_id' => $memberId,
                'status' => 'ACTIVE',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('credit_policies')->update(['active' => false]);
            DB::table('credit_policies')->insert([
                'id' => $policyId,
                'limit_amount' => 100000, // Cukup hanya untuk 1 order (75000), 2 order = 150000 (> 100000)
                'due_days' => 7,
                'active' => true,
                'effective_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('categories')->insert([
                'id' => $catId,
                'name' => "Kat {$sku}",
                'slug' => "kat-{$sku}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('products')->insert([
                'id' => $prodId,
                'category_id' => $catId,
                'sku' => $sku,
                'name' => 'Barang Concurrency Kredit',
                'selling_price' => 25000,
                'current_cost' => 20000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('inventory_balances')->insert([
                'product_id' => $prodId,
                'on_hand' => 50, // Stok sangat cukup agar kontensi fokus di credit lock!
                'reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results = $this->runParallelCheckouts(
                [$memberId, $prodId, 3, 'CREDIT', $userId],
                [$memberId, $prodId, 3, 'CREDIT', $userId]
            );

            $okCount = count(array_filter($results, fn($r) => str_starts_with($r, 'OK:')));
            $errCount = count(array_filter($results, fn($r) => str_starts_with($r, 'ERR:CREDIT_LIMIT_EXCEEDED:')));

            $this->assertSame(1, $okCount, 'Harus tepat 1 checkout kredit lolos: ' . implode(' | ', $results));
            $this->assertSame(1, $errCount, 'Harus tepat 1 checkout kredit ditolak CREDIT_LIMIT_EXCEEDED: ' . implode(' | ', $results));

            $activeHolds = (float) DB::table('credit_holds')->where('member_id', $memberId)->where('status', 'ACTIVE')->sum('amount');
            $this->assertEquals(75000.0, $activeHolds);
            $this->assertLessThanOrEqual(100000.0, $activeHolds);
        } finally {
            $orderIds = DB::table('orders')->where('member_id', $memberId)->pluck('id');
            if ($orderIds->isNotEmpty()) {
                DB::table('stock_reservations')->whereIn('order_id', $orderIds)->delete();
                DB::table('credit_holds')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
            }
            DB::table('audit_logs')->where('actor_id', $userId)->delete();
            DB::table('member_credit_accounts')->where('member_id', $memberId)->delete();
            DB::table('inventory_balances')->where('product_id', $prodId)->delete();
            DB::table('products')->where('id', $prodId)->delete();
            DB::table('categories')->where('id', $catId)->delete();
            DB::table('credit_policies')->where('id', $policyId)->delete();
            DB::table('members')->where('id', $memberId)->delete();
            DB::table('user_roles')->where('user_id', $userId)->delete();
            DB::table('users')->where('id', $userId)->delete();
        }
    }

    public function test_parallel_identical_idempotent_checkouts_replay_without_500_or_duplicate(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        $userId = (string) Str::uuid();
        $memberId = (string) Str::uuid();
        $catId = (string) Str::uuid();
        $prodId = (string) Str::uuid();
        $sku = 'CIDM-' . strtoupper(Str::random(5));
        $idemKey = 'conc-idem-' . strtolower(Str::random(8));

        try {
            DB::table('roles')->updateOrInsert(['code' => 'MEMBER'], ['description' => 'Member', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->insert([
                'id' => $userId,
                'email' => "idem.{$sku}@polman-bandung.ac.id",
                'password_hash' => bcrypt('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_roles')->insert(['user_id' => $userId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('members')->insert([
                'id' => $memberId,
                'user_id' => $userId,
                'employee_number' => "EMP-{$sku}",
                'member_number' => "MEM-{$sku}",
                'full_name' => 'Concurrency Idempotent Member',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('categories')->insert([
                'id' => $catId,
                'name' => "Kat {$sku}",
                'slug' => "kat-{$sku}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('products')->insert([
                'id' => $prodId,
                'category_id' => $catId,
                'sku' => $sku,
                'name' => 'Barang Concurrency Idempotency',
                'selling_price' => 15000,
                'current_cost' => 12000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('inventory_balances')->insert([
                'product_id' => $prodId,
                'on_hand' => 2,
                'reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results = $this->runParallelCheckouts(
                [$memberId, $prodId, 2, 'CASH', $userId, $idemKey],
                [$memberId, $prodId, 2, 'CASH', $userId, $idemKey]
            );

            $okResults = array_values(array_filter($results, fn($r) => str_starts_with($r, 'OK:')));
            $this->assertCount(2, $okResults, 'Kedua request paralel idempotent wajib berhasil mengembalikan order yang sama: ' . implode(' | ', $results));

            $id1 = substr($okResults[0], 3);
            $id2 = substr($okResults[1], 3);
            $this->assertSame($id1, $id2, 'Kedua request paralel harus mengembalikan order_id identik.');

            $this->assertSame(1, DB::table('orders')->where('member_id', $memberId)->count());
            $this->assertSame(2, (int) DB::table('inventory_balances')->where('product_id', $prodId)->value('reserved'));
        } finally {
            DB::table('idempotency_keys')->where('idempotency_key', $idemKey)->delete();
            $orderIds = DB::table('orders')->where('member_id', $memberId)->pluck('id');
            if ($orderIds->isNotEmpty()) {
                DB::table('stock_reservations')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
            }
            DB::table('audit_logs')->where('actor_id', $userId)->delete();
            DB::table('inventory_balances')->where('product_id', $prodId)->delete();
            DB::table('products')->where('id', $prodId)->delete();
            DB::table('categories')->where('id', $catId)->delete();
            DB::table('members')->where('id', $memberId)->delete();
            DB::table('user_roles')->where('user_id', $userId)->delete();
            DB::table('users')->where('id', $userId)->delete();
        }
    }

    public function test_parallel_inventory_receive_and_pos_sale_never_deadlock_or_corrupt_stock(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        $cashierUserId = (string) Str::uuid();
        $catId = (string) Str::uuid();
        $prodId = (string) Str::uuid();
        $sku = 'CHPP-' . strtoupper(Str::random(5));

        try {
            DB::table('roles')->updateOrInsert(['code' => 'CASHIER'], ['description' => 'Cashier', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('users')->insert([
                'id' => $cashierUserId,
                'email' => "chpp.{$sku}@polman-bandung.ac.id",
                'password_hash' => bcrypt('kasir123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('user_roles')->insert(['user_id' => $cashierUserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('categories')->insert([
                'id' => $catId,
                'name' => "Kat {$sku}",
                'slug' => "kat-{$sku}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('products')->insert([
                'id' => $prodId,
                'category_id' => $catId,
                'sku' => $sku,
                'name' => 'Barang Concurrency HPP',
                'selling_price' => 20000,
                'current_cost' => 10000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('inventory_balances')->insert([
                'product_id' => $prodId,
                'on_hand' => 10,
                'reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $receiveCode = sprintf(
                'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); config(["database.default" => "pgsql", "database.connections.pgsql.database" => "koperadata_test"]); DB::purge("pgsql"); try { DB::transaction(function () { $p = App\Models\Product::where("id", "%s")->lockForUpdate()->firstOrFail(); $inv = app(App\Contracts\InventoryServiceInterface::class); $onHand = $inv->getOnHand($p->id); $newCost = round((($onHand * (float)$p->current_cost) + (10 * 20000)) / ($onHand + 10), 2); $p->update(["current_cost" => $newCost]); $inv->receive($p->id, 10, "PURCHASE_RECEIPT", "%s", "RCV-%s"); }); echo "RCV_OK"; } catch (Throwable $e) { echo "RCV_ERR:" . $e->getMessage(); }',
                $prodId,
                $cashierUserId,
                $sku
            );

            $posCode = sprintf(
                'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); config(["database.default" => "pgsql", "database.connections.pgsql.database" => "koperadata_test"]); DB::purge("pgsql"); try { $res = app(App\Services\TransactionService::class)->posSale(["payment_choice" => "CASH", "cash_received" => 80000, "items" => [["product_id" => "%s", "quantity" => 4]]], "%s", null); echo "POS_OK:" . $res["order_id"]; } catch (Throwable $e) { echo "POS_ERR:" . $e->getMessage(); }',
                $prodId,
                $cashierUserId
            );


            $env = $this->workerEnvironment();

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

            $p1 = proc_open([PHP_BINARY, '-r', $receiveCode], $descriptors, $pipes1, base_path(), $env);
            $p2 = proc_open([PHP_BINARY, '-r', $posCode], $descriptors, $pipes2, base_path(), $env);

            $out1 = trim((string) stream_get_contents($pipes1[1])) . trim((string) stream_get_contents($pipes1[2]));
            $out2 = trim((string) stream_get_contents($pipes2[1])) . trim((string) stream_get_contents($pipes2[2]));

            fclose($pipes1[0]);
            fclose($pipes1[1]);
            fclose($pipes1[2]);
            fclose($pipes2[0]);
            fclose($pipes2[1]);
            fclose($pipes2[2]);
            proc_close($p1);
            proc_close($p2);

            $this->assertSame('RCV_OK', $out1, "Receive gagal: {$out1}");
            $this->assertStringStartsWith('POS_OK:', $out2, "POS gagal: {$out2}");

            // Net stok harus tepat 10 + 10 - 4 = 16 tanpa race / deadlock
            $inv = DB::table('inventory_balances')->where('product_id', $prodId)->first();
            $this->assertSame(16, (int) $inv->on_hand);
            $this->assertSame(0, (int) $inv->reserved);
        } finally {
            $orderIds = DB::table('order_items')->where('product_id', $prodId)->pluck('order_id');
            if ($orderIds->isNotEmpty()) {
                DB::table('payments')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
            }
            DB::table('stock_movements')->where('product_id', $prodId)->delete();
            DB::table('audit_logs')->where('actor_id', $cashierUserId)->delete();
            DB::table('inventory_balances')->where('product_id', $prodId)->delete();
            DB::table('products')->where('id', $prodId)->delete();
            DB::table('categories')->where('id', $catId)->delete();
            DB::table('user_roles')->where('user_id', $cashierUserId)->delete();
            DB::table('users')->where('id', $cashierUserId)->delete();
        }
    }

    private function runParallelCheckouts(array $job1, array $job2): array
    {
        $buildCode = function (array $job): string {
            $memberId = $job[0];
            $prodId = $job[1];
            $qty = $job[2];
            $method = $job[3];
            $userId = $job[4];
            $idemKey = $job[5] ?? null;

            return sprintf(
                'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); config(["database.default" => "pgsql", "database.connections.pgsql.database" => "koperadata_test"]); DB::purge("pgsql"); try { $payload = ["member_id" => "%s", "payment_choice" => "%s", "items" => [["product_id" => "%s", "quantity" => %d]]]; $k = %s; if ($k !== null) { $payload["idempotency_key"] = $k; } $o = app(App\Services\TransactionService::class)->checkout($payload, "ONLINE_PICKUP", "%s"); echo "OK:" . $o->id; } catch (App\Exceptions\BusinessRuleException $e) { echo "ERR:" . $e->errorCode . ":" . $e->getMessage(); } catch (Throwable $e) { echo "ERR:OTHER:" . $e->getMessage(); }',
                $memberId,
                $method,
                $prodId,
                $qty,
                $idemKey ? var_export($idemKey, true) : 'null',
                $userId
            );
        };


        $env = $this->workerEnvironment();

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $p1 = proc_open([PHP_BINARY, '-r', $buildCode($job1)], $descriptors, $pipes1, base_path(), $env);
        $p2 = proc_open([PHP_BINARY, '-r', $buildCode($job2)], $descriptors, $pipes2, base_path(), $env);

        $out1 = trim((string) stream_get_contents($pipes1[1])) . trim((string) stream_get_contents($pipes1[2]));
        $out2 = trim((string) stream_get_contents($pipes2[1])) . trim((string) stream_get_contents($pipes2[2]));

        fclose($pipes1[0]);
        fclose($pipes1[1]);
        fclose($pipes1[2]);
        fclose($pipes2[0]);
        fclose($pipes2[1]);
        fclose($pipes2[2]);
        proc_close($p1);
        proc_close($p2);

        return [$out1, $out2];
    }
    private function workerEnvironment(): array
    {
        $connection = config('database.connections.pgsql');
        if (DB::connection()->getDatabaseName() !== 'koperadata_test' || $connection['database'] !== 'koperadata_test') {
            throw new \RuntimeException('KESALAHAN KEAMANAN: worker hanya boleh memakai koperadata_test.');
        }
        return array_merge(getenv() ?: [], $_ENV, [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) $connection['host'],
            'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => 'koperadata_test',
            'DB_USERNAME' => (string) $connection['username'],
            'DB_PASSWORD' => (string) $connection['password'],
            'DB_URL' => '',
        ]);
    }
}
