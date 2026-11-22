<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessGuardrailsPgsqlTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $migrated = false;

    private User $memberUser;
    private User $cashierUser;
    private User $managerUser;
    private string $memberId;
    private string $categoryId;
    private string $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        if (! self::$migrated) {
            Artisan::call('migrate', ['--force' => true]);
            self::$migrated = true;
        }

        foreach (['MEMBER', 'CASHIER', 'MANAGER'] as $role) {
            DB::table('roles')->updateOrInsert(['code' => $role], [
                'description' => "Role {$role}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $memberUserId = (string) Str::uuid();
        $cashierUserId = (string) Str::uuid();
        $managerUserId = (string) Str::uuid();
        $this->memberId = (string) Str::uuid();

        DB::table('users')->insert([
            [
                'id' => $memberUserId,
                'email' => 'guard.member.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $cashierUserId,
                'email' => 'guard.cashier.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('kasir123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $managerUserId,
                'email' => 'guard.manager.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('manajer123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('user_roles')->insert([
            ['user_id' => $memberUserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $cashierUserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $managerUserId, 'role_code' => 'MANAGER', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('members')->insert([
            'id' => $this->memberId,
            'user_id' => $memberUserId,
            'employee_number' => 'EMP-GRD-' . Str::random(4),
            'member_number' => 'MEM-GRD-' . Str::random(4),
            'full_name' => 'Anggota Guardrail',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('member_credit_accounts')->insert([
            'member_id' => $this->memberId,
            'status' => 'ACTIVE',
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! DB::table('credit_policies')->where('active', true)->exists()) {
            DB::table('credit_policies')->insert([
                'id' => (string) Str::uuid(),
                'limit_amount' => 1000000,
                'due_days' => 7,
                'active' => true,
                'effective_at' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->categoryId = (string) Str::uuid();
        DB::table('categories')->insert([
            'id' => $this->categoryId,
            'name' => 'Kat Guardrail ' . Str::random(5),
            'slug' => 'kat-grd-' . Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = (string) Str::uuid();
        DB::table('products')->insert([
            'id' => $this->productId,
            'category_id' => $this->categoryId,
            'sku' => 'GRD-SKU-' . Str::random(5),
            'barcode' => '8998000' . random_int(10000, 99999),
            'name' => 'Produk Guardrail',
            'selling_price' => 50000,
            'current_cost' => 40000,
            'reorder_point' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_balances')->insert([
            'product_id' => $this->productId,
            'on_hand' => 20,
            'reserved' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->memberUser = User::findOrFail($memberUserId);
        $this->cashierUser = User::findOrFail($cashierUserId);
        $this->managerUser = User::findOrFail($managerUserId);
    }

    public function test_expire_pending_orders_command_and_hourly_schedule(): void
    {
        $events = collect(app(Schedule::class)->events());
        $hasHourlyExpire = $events->contains(function ($event) {
            return str_contains((string) $event->command, 'orders:expire-pending')
                && $event->expression === '0 * * * *';
        });
        $this->assertTrue($hasHourlyExpire, 'orders:expire-pending must be scheduled hourly()');

        // 1. Expired unpaid order (25 hours old) -> MUST be cancelled & stock released
        $expiredRes = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_choice' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 3]],
        ])->assertStatus(201);
        $expiredOrderId = $expiredRes->json('data.order_id');
        DB::table('orders')->where('id', $expiredOrderId)->update(['created_at' => CarbonImmutable::now('UTC')->subHours(25)]);

        // 2. Fresh unpaid order (20 hours old) -> MUST remain PENDING_PAYMENT
        $freshRes = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_choice' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201);
        $freshOrderId = $freshRes->json('data.order_id');
        DB::table('orders')->where('id', $freshOrderId)->update(['created_at' => CarbonImmutable::now('UTC')->subHours(20)]);

        // 3. Old order (>24h) WITH pending transfer verification -> MUST NOT be cancelled
        $transferRes = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_choice' => 'BANK_TRANSFER',
            'items' => [['product_id' => $this->productId, 'quantity' => 4]],
        ])->assertStatus(201);
        $transferOrderId = $transferRes->json('data.order_id');
        $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$transferOrderId}/payments", [
            'method' => 'BANK_TRANSFER',
            'amount' => 200000,
            'external_reference' => 'TRF-EXP-' . Str::random(5),
        ])->assertStatus(201);
        DB::table('orders')->where('id', $transferOrderId)->update(['created_at' => CarbonImmutable::now('UTC')->subHours(30)]);

        $this->assertSame(9, (int) DB::table('inventory_balances')->where('product_id', $this->productId)->value('reserved'));

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $this->assertSame('CANCELLED', DB::table('orders')->where('id', $expiredOrderId)->value('status'));
        $this->assertSame('CANCELLED', DB::table('stock_reservations')->where('order_id', $expiredOrderId)->value('status'));
        $this->assertSame('PENDING_PAYMENT', DB::table('orders')->where('id', $freshOrderId)->value('status'));
        $this->assertSame('PENDING_PAYMENT', DB::table('orders')->where('id', $transferOrderId)->value('status'));
        $this->assertSame(6, (int) DB::table('inventory_balances')->where('product_id', $this->productId)->value('reserved'));
    }

    public function test_inventory_adjustment_idempotency_and_conflict(): void
    {
        // Double submit with explicit idempotency_key
        $payload = [
            'product_id' => $this->productId,
            'adjustment_type' => 'DEDUCTION',
            'quantity' => 3,
            'reason' => 'Kemasan rusak saat inspeksi',
            'idempotency_key' => 'adj-idem-' . Str::random(6),
        ];

        $first = $this->actingAs($this->managerUser)->postJson('/api/v1/inventory/adjustments', $payload)->assertStatus(201);
        $this->assertSame(17, (int) $first->json('data.new_on_hand'));

        $second = $this->actingAs($this->managerUser)->postJson('/api/v1/inventory/adjustments', $payload)->assertStatus(200);
        $this->assertTrue((bool) $second->json('data.idempotent'));
        $this->assertSame(17, (int) DB::table('inventory_balances')->where('product_id', $this->productId)->value('on_hand'));
        $this->assertSame(1, DB::table('stock_movements')->where('product_id', $this->productId)->where('type', 'ADJUSTMENT')->count());

        // Same key with mismatched quantity -> 409 conflict
        $this->actingAs($this->managerUser)->postJson('/api/v1/inventory/adjustments', array_merge($payload, ['quantity' => 5]))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_CONFLICT');

        // Double submit OPNAME within same minute without idempotency_key -> idempotent replay
        $opnamePayload = [
            'product_id' => $this->productId,
            'adjustment_type' => 'OPNAME',
            'quantity' => 15,
            'reason' => 'Hitung fisik gudang',
        ];
        $this->actingAs($this->managerUser)->postJson('/api/v1/inventory/adjustments', $opnamePayload)->assertStatus(201);
        $this->actingAs($this->managerUser)->postJson('/api/v1/inventory/adjustments', $opnamePayload)
            ->assertStatus(200)
            ->assertJsonPath('data.idempotent', true);
        $this->assertSame(15, (int) DB::table('inventory_balances')->where('product_id', $this->productId)->value('on_hand'));
    }

    public function test_analytics_summary_falls_back_to_oltp_when_newer_orders_or_expenses_exist(): void
    {
        $todayStr = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $dateKey = (int) CarbonImmutable::now('Asia/Jakarta')->format('Ymd');
        $etlFinishedUtc = CarbonImmutable::now('UTC')->subMinutes(5);

        DB::table('analytics.etl_runs')->delete();
        DB::table('analytics.fact_sales')->delete();
        DB::table('analytics.fact_cash_receipts')->delete();
        DB::table('analytics.fact_operating_expenses')->delete();
        DB::table('analytics.fact_receivables_daily')->delete();
        DB::table('analytics.dim_date')->where('date_key', $dateKey)->delete();
        DB::table('analytics.dim_product')->where('product_key', 'wh-prod-1')->delete();
        DB::table('analytics.dim_member')->where('member_key', 'wh-mem-1')->delete();

        DB::table('analytics.dim_date')->insert([
            'date_key' => $dateKey,
            'calendar_date' => $todayStr,
            'iso_week' => (int) CarbonImmutable::now('Asia/Jakarta')->isoWeek(),
            'month' => (int) CarbonImmutable::now('Asia/Jakarta')->month,
            'year' => (int) CarbonImmutable::now('Asia/Jakarta')->year,
        ]);
        DB::table('analytics.dim_product')->insert([
            'product_key' => 'wh-prod-1',
            'sku' => 'WH-SKU-1',
            'name' => 'Warehouse Dummy',
        ]);
        DB::table('analytics.dim_member')->insert([
            'member_key' => 'wh-mem-1',
            'department' => 'Umum',
        ]);
        DB::table('analytics.etl_runs')->insert([
            'run_id' => (string) Str::uuid(),
            'status' => 'SUCCEEDED',
            'started_at' => $etlFinishedUtc->subMinute(),
            'finished_at' => $etlFinishedUtc,
        ]);
        DB::table('analytics.fact_sales')->insert([
            'order_item_key' => 'wh-oi-1',
            'order_id' => 'wh-ord-1',
            'date_key' => $dateKey,
            'local_date' => $todayStr,
            'completed_at' => $etlFinishedUtc->subMinute(),
            'product_key' => 'wh-prod-1',
            'member_key' => 'wh-mem-1',
            'quantity' => 1,
            'revenue' => 12345.00,
            'cogs' => 10000.00,
            'cogs_complete' => true,
            'channel' => 'POS',
            'payment_choice' => 'CASH',
        ]);

        // Ensure no OLTP completed orders or expenses are newer than ETL finished_at inside this rollback txn
        DB::table('orders')->where('status', 'COMPLETED')->whereRaw('COALESCE(completed_at, updated_at, created_at) > ?', [$etlFinishedUtc])->delete();
        DB::table('operating_expenses')->whereRaw('COALESCE(updated_at, created_at) > ?', [$etlFinishedUtc])->delete();

        // While OLTP has no newer rows, warehouse value (12345.00) is returned
        $this->actingAs($this->managerUser)->getJson("/api/v1/analytics/summary?from={$todayStr}&to={$todayStr}")
            ->assertOk()
            ->assertJsonPath('metrics.sales_revenue', '12345.00');

        // Insert a newer COMPLETED POS order -> must fall back immediately to OLTP instead of reading stale 12345.00
        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'cash_received' => 50000,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(201);

        $liveRev = (float) $this->actingAs($this->managerUser)
            ->getJson("/api/v1/analytics/summary?from={$todayStr}&to={$todayStr}")
            ->assertOk()
            ->json('metrics.sales_revenue');
        $this->assertGreaterThanOrEqual(50000.0, $liveRev);
        $this->assertNotEquals(12345.0, $liveRev);
    }

    public function test_payment_and_price_guardrails_reject_invalid_operations(): void
    {
        // 1. recordOrderPayment() must reject CASH/BANK_TRANSFER on CREDIT order
        $creditOrderRes = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_choice' => 'CREDIT',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(201);
        $creditOrderId = $creditOrderRes->json('data.order_id');

        $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$creditOrderId}/payments", [
            'method' => 'CASH',
            'amount' => 50000,
        ])->assertStatus(422)->assertJsonPath('error_code', 'CREDIT_ORDER_PAYMENT_FORBIDDEN');

        // 2. posSale() must require non-null cash_received >= total for CASH
        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'INSUFFICIENT_CASH');

        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'cash_received' => 40000,
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'INSUFFICIENT_CASH');

        // 3. ProductController price guardrails (store, update, updatePrice)
        $this->actingAs($this->managerUser)->postJson('/api/v1/products', [
            'sku' => 'GRD-ZERO-' . Str::random(4),
            'name' => 'Produk Harga Nol',
            'selling_price' => 0,
            'current_cost' => 10000,
        ])->assertStatus(422)->assertJsonPath('error_code', 'INVALID_SELLING_PRICE');

        $this->actingAs($this->managerUser)->postJson('/api/v1/products', [
            'sku' => 'GRD-LOSS-' . Str::random(4),
            'name' => 'Produk Rugi',
            'selling_price' => 9000,
            'current_cost' => 10000,
        ])->assertStatus(422)->assertJsonPath('error_code', 'SELLING_PRICE_BELOW_COST');

        $allowedCreate = $this->actingAs($this->managerUser)->postJson('/api/v1/products', [
            'sku' => 'GRD-PROMO-' . Str::random(4),
            'name' => 'Produk Promo Khusus',
            'selling_price' => 9000,
            'current_cost' => 10000,
            'allow_below_cost' => true,
        ])->assertStatus(201);
        $createdId = $allowedCreate->json('data.id');

        $this->actingAs($this->managerUser)->patchJson("/api/v1/products/{$this->productId}", [
            'selling_price' => 30000, // < current_cost 40000
        ])->assertStatus(422)->assertJsonPath('error_code', 'SELLING_PRICE_BELOW_COST');

        $this->actingAs($this->managerUser)->postJson("/api/v1/products/{$this->productId}/price", [
            'selling_price' => 35000,
        ])->assertStatus(422)->assertJsonPath('error_code', 'SELLING_PRICE_BELOW_COST');

        $this->actingAs($this->managerUser)->postJson("/api/v1/products/{$createdId}/price", [
            'selling_price' => 8500,
            'allow_below_cost' => true,
        ])->assertOk();
    }
}
