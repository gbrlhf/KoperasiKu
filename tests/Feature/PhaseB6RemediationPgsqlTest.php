<?php

namespace Tests\Feature;

use App\Models\CreditPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseB6RemediationPgsqlTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $migrated = false;

    private User $memberUser;
    private User $otherMemberUser;
    private User $cashierUser;
    private User $otherCashierUser;
    private User $managerUser;

    private string $memberId;
    private string $otherMemberId;
    private string $productId;
    private string $secondProductId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        if (!self::$migrated) {
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

        $m1UserId = (string) Str::uuid();
        $m2UserId = (string) Str::uuid();
        $c1UserId = (string) Str::uuid();
        $c2UserId = (string) Str::uuid();
        $mgrUserId = (string) Str::uuid();

        $this->memberId = (string) Str::uuid();
        $this->otherMemberId = (string) Str::uuid();

        DB::table('users')->insert([
            [
                'id' => $m1UserId,
                'email' => 'b6.member1.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $m2UserId,
                'email' => 'b6.member2.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $c1UserId,
                'email' => 'b6.cashier1.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('kasir123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $c2UserId,
                'email' => 'b6.cashier2.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('kasir123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $mgrUserId,
                'email' => 'b6.manager.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('manajer123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('user_roles')->insert([
            ['user_id' => $m1UserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $m2UserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $c1UserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $c2UserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $mgrUserId, 'role_code' => 'MANAGER', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('members')->insert([
            [
                'id' => $this->memberId,
                'user_id' => $m1UserId,
                'employee_number' => 'EMP-B6-01-' . Str::random(3),
                'member_number' => 'MEM-B6-01-' . Str::random(3),
                'full_name' => 'Anggota B6 Satu',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->otherMemberId,
                'user_id' => $m2UserId,
                'employee_number' => 'EMP-B6-02-' . Str::random(3),
                'member_number' => 'MEM-B6-02-' . Str::random(3),
                'full_name' => 'Anggota B6 Dua',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('member_credit_accounts')->insert([
            ['member_id' => $this->memberId, 'status' => 'ACTIVE', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['member_id' => $this->otherMemberId, 'status' => 'ACTIVE', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('credit_policies')->update(['active' => false]);
        CreditPolicy::create([
            'limit_amount' => 1000000,
            'due_days' => 7,
            'active' => true,
            'effective_at' => now(),
        ]);

        $catId = (string) Str::uuid();
        DB::table('categories')->insert([
            'id' => $catId,
            'name' => 'Kat B6 ' . Str::random(5),
            'slug' => 'kat-b6-' . Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = (string) Str::uuid();
        $this->secondProductId = (string) Str::uuid();

        DB::table('products')->insert([
            [
                'id' => $this->productId,
                'category_id' => $catId,
                'sku' => 'B6-SKU-1-' . Str::random(4),
                'barcode' => '8996000' . random_int(10000, 99999),
                'name' => 'Produk B6 Utama',
                'selling_price' => 50000,
                'current_cost' => 40000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->secondProductId,
                'category_id' => $catId,
                'sku' => 'B6-SKU-2-' . Str::random(4),
                'barcode' => '8996000' . random_int(10000, 99999),
                'name' => 'Produk B6 Kedua',
                'selling_price' => 30000,
                'current_cost' => 24000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('inventory_balances')->insert([
            ['product_id' => $this->productId, 'on_hand' => 10, 'reserved' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $this->secondProductId, 'on_hand' => 10, 'reserved' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->memberUser = User::findOrFail($m1UserId);
        $this->otherMemberUser = User::findOrFail($m2UserId);
        $this->cashierUser = User::findOrFail($c1UserId);
        $this->otherCashierUser = User::findOrFail($c2UserId);
        $this->managerUser = User::findOrFail($mgrUserId);
    }

    public function test_b6_1_checkout_idempotency_replay_payload_conflict_and_cross_user_rejection(): void
    {
        // Invalid format (whitespace inside key) rejected cleanly
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'idempotency_key' => 'invalid key with space',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'INVALID_IDEMPOTENCY_KEY');

        // 1. Original checkout succeeds
        $first = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'idempotency_key' => 'b6-chk-001',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201);

        $orderId = $first->json('data.order_id');

        // 2. Exact duplicate replay returns original order + idempotent=true
        $replay = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'idempotency_key' => 'b6-chk-001',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201)->assertJsonPath('data.idempotent', true);
        $this->assertSame($orderId, $replay->json('data.order_id'));
        $this->assertEquals(2, (int) DB::table('inventory_balances')->where('product_id', $this->productId)->value('reserved'));

        // 3. Same user, same key, DIFFERENT payload (qty=3 or different payment_method) MUST be 409
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'idempotency_key' => 'b6-chk-001',
            'items' => [['product_id' => $this->productId, 'quantity' => 3]],
        ])->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

        // 4. Same user, same key reused on DIFFERENT operation (POS_SALE) MUST be 409
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CREDIT',
            'idempotency_key' => 'b6-chk-001',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

        // 5. DIFFERENT user reusing same key MUST be 403 FORBIDDEN
        $this->actingAs($this->otherMemberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'idempotency_key' => 'b6-chk-001',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(403)->assertJsonPath('error_code', 'FORBIDDEN');
    }

    public function test_b6_1_failed_transaction_retry_with_same_idempotency_key_succeeds(): void
    {
        // Try qty=99 (> on_hand=10) -> fails with 422 INSUFFICIENT_STOCK and rolls back idempotency record
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'idempotency_key' => 'b6-retry-after-fail',
            'items' => [['product_id' => $this->productId, 'quantity' => 99]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'INSUFFICIENT_STOCK');

        $this->assertDatabaseMissing('idempotency_keys', ['idempotency_key' => 'b6-retry-after-fail']);

        // Retry with valid quantity using the same idempotency_key succeeds
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'idempotency_key' => 'b6-retry-after-fail',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201)->assertJsonPath('success', true);
    }

    public function test_b6_1_pickup_order_payment_pos_verify_and_receivable_cross_target_and_payload_conflicts(): void
    {
        $orderA = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CREDIT',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(201);

        $orderB = $this->actingAs($this->otherMemberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'items' => [['product_id' => $this->secondProductId, 'quantity' => 1]],
        ])->assertStatus(201);

        $orderAId = $orderA->json('data.order_id');
        $orderBId = $orderB->json('data.order_id');
        $pickupA = $orderA->json('data.pickup_code');

        // Confirm pickup Order A
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/orders/{$orderAId}/confirm-pickup", ['idempotency_key' => 'b6-pkp-A', 'pickup_code' => $pickupA, 'handover_confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.order_id', $orderAId);

        // Same order via pickup_code instead of UUID replays cleanly
        $this->actingAs($this->cashierUser)
            ->postJson('/cashier/confirm-pickup', ['pickup_code' => $pickupA, 'idempotency_key' => 'b6-pkp-A', 'handover_confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.idempotent', true)
            ->assertJsonPath('data.order_id', $orderAId);

        // Cross-order key reuse (Order A's pickup key sent against Order B) MUST fail with 409 IDEMPOTENCY_CONFLICT!
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/orders/{$orderBId}/confirm-pickup", ['idempotency_key' => 'b6-pkp-A', 'pickup_code' => $orderB->json('data.pickup_code'), 'handover_confirmed' => true])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_CONFLICT');

        // Different cashier reusing Order A's pickup key MUST fail with 403 FORBIDDEN!
        $this->actingAs($this->otherCashierUser)
            ->postJson("/api/v1/orders/{$orderAId}/confirm-pickup", ['idempotency_key' => 'b6-pkp-A', 'pickup_code' => $pickupA, 'handover_confirmed' => true])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'FORBIDDEN');

        // Record Order B transfer payment with idempotency_key + verify payment with idempotency_key
        $payB = $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$orderBId}/payments", [
            'method' => 'BANK_TRANSFER',
            'amount' => 30000,
            'external_reference' => 'B6-TRF-001',
            'idempotency_key' => 'b6-ordpay-1',
        ])->assertStatus(201);

        $payBId = $payB->json('data.payment_id');

        // Cross-order payment key reuse or payload mismatch -> 409
        $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$orderAId}/payments", [
            'method' => 'BANK_TRANSFER',
            'amount' => 30000,
            'idempotency_key' => 'b6-ordpay-1',
        ])->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_CONFLICT');

        $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$orderBId}/payments", [
            'method' => 'BANK_TRANSFER',
            'amount' => 15000,
            'idempotency_key' => 'b6-ordpay-1',
        ])->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

        // Verify payment with idempotency key & action conflict rejection
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/payments/{$payBId}/verify", ['action' => 'VERIFY', 'idempotency_key' => 'b6-vrf-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED');

        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/payments/{$payBId}/verify", ['action' => 'REJECT', 'idempotency_key' => 'b6-vrf-1'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

        // POS Sale idempotency + payment metadata (B6.5) + payload mismatch check
        $posRes = $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'idempotency_key' => 'b6-pos-1',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(201);

        $this->assertDatabaseHas('payments', [
            'order_id' => $posRes->json('data.order_id'),
            'idempotency_key' => 'b6-pos-1',
        ]);

        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'idempotency_key' => 'b6-pos-1',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');

        // Receivable payment idempotency + payload mismatch check
        $recAId = (string) DB::table('receivables')->where('order_id', $orderAId)->value('id');
        $this->actingAs($this->cashierUser)->postJson('/api/v1/receivables/payments', [
            'member_id' => $this->memberId,
            'method' => 'CASH',
            'amount' => 20000,
            'idempotency_key' => 'b6-rec-1',
            'allocations' => [['receivable_id' => $recAId, 'amount' => 20000]],
        ])->assertOk();

        $this->actingAs($this->cashierUser)->postJson('/api/v1/receivables/payments', [
            'member_id' => $this->memberId,
            'method' => 'CASH',
            'amount' => 25000,
            'idempotency_key' => 'b6-rec-1',
            'allocations' => [['receivable_id' => $recAId, 'amount' => 25000]],
        ])->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_PAYLOAD_MISMATCH');
    }

    public function test_b6_2_csrf_protection_on_stateful_api_routes(): void
    {
        \App\Http\Middleware\VerifyApiCsrfToken::$enforceInTests = true;

        try {
            // Inisialisasi sesi + CSRF token via GET halaman login
            $this->withoutVite()->get('/login')->assertOk();
            $csrfToken = csrf_token();
            $this->assertNotEmpty($csrfToken);

            // Unauthenticated state-changing request tanpa CSRF ditolak 419
            $this->postJson('/api/v1/orders', [
                'payment_method' => 'CASH',
                'items' => [['product_id' => $this->productId, 'quantity' => 1]],
            ])->assertStatus(419)->assertJsonPath('error_code', 'CSRF_TOKEN_MISMATCH');

            // Unauthenticated state-changing request DENGAN CSRF valid lolos CSRF lalu ditolak auth 401
            $this->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->postJson('/api/v1/orders', [
                    'payment_method' => 'CASH',
                    'items' => [['product_id' => $this->productId, 'quantity' => 1]],
                ])->assertStatus(401);

            // Login API tanpa CSRF ditolak 419; dengan CSRF valid berhasil 200
            $this->flushHeaders();
            $this->postJson('/api/v1/auth/login', [
                'username' => $this->memberUser->email,
                'password' => 'password123',
            ])->assertStatus(419);

            $loginRes = $this->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->postJson('/api/v1/auth/login', [
                    'username' => $this->memberUser->email,
                    'password' => 'password123',
                ])->assertOk();
            $loginRes->assertJsonPath('success', true);
            $csrfToken = csrf_token();

            // Authenticated state-changing request tanpa CSRF ditolak 419
            $this->flushHeaders();
            $this->actingAs($this->memberUser)
                ->postJson('/api/v1/orders', [
                    'payment_method' => 'CASH',
                    'items' => [['product_id' => $this->productId, 'quantity' => 1]],
                ])->assertStatus(419)->assertJsonPath('error_code', 'CSRF_TOKEN_MISMATCH');

            // Authenticated state-changing request dengan CSRF salah ditolak 419
            $this->actingAs($this->memberUser)
                ->withHeader('X-CSRF-TOKEN', 'invalid-csrf-token-value')
                ->postJson('/api/v1/orders', [
                    'payment_method' => 'CASH',
                    'items' => [['product_id' => $this->productId, 'quantity' => 1]],
                ])->assertStatus(419)->assertJsonPath('error_code', 'CSRF_TOKEN_MISMATCH');

            // Authenticated state-changing request DENGAN CSRF valid berhasil 201
            $this->actingAs($this->memberUser)
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->postJson('/api/v1/orders', [
                    'payment_method' => 'CASH',
                    'items' => [['product_id' => $this->productId, 'quantity' => 1]],
                ])->assertStatus(201)->assertJsonPath('success', true);

            // Manager PATCH tanpa CSRF ditolak 419, dengan CSRF valid berhasil 200
            $this->flushHeaders();
            $this->actingAs($this->managerUser)
                ->patchJson('/api/v1/credit-policy', ['limit_amount' => 600000])
                ->assertStatus(419);

            $this->actingAs($this->managerUser)
                ->withHeader('X-CSRF-TOKEN', $csrfToken)
                ->patchJson('/api/v1/credit-policy', ['limit_amount' => 600000])
                ->assertOk()
                ->assertJsonPath('success', true);

            // Authenticated GET tetap diizinkan tanpa header CSRF
            $this->actingAs($this->memberUser)
                ->getJson('/api/v1/orders/my')
                ->assertOk();

            // Pastikan seluruh halaman frontend menyediakan <meta name="csrf-token">
            $this->withoutVite()->get('/')->assertSee('name="csrf-token"', false);
            $this->withoutVite()->get('/login')->assertSee('name="csrf-token"', false);
            $this->withoutVite()->actingAs($this->cashierUser)->get('/cashier/pos')->assertSee('name="csrf-token"', false);
            $this->withoutVite()->actingAs($this->managerUser)->get('/manager/dashboard')->assertSee('name="csrf-token"', false);
        } finally {
            \App\Http\Middleware\VerifyApiCsrfToken::$enforceInTests = false;
        }
    }

    public function test_b6_3_multi_role_active_role_enforcement_and_require_manager_isolation(): void
    {
        $multiUserId = (string) Str::uuid();
        $multiMemberId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $multiUserId,
            'email' => 'b6.multi.' . Str::random(4) . '@polman-bandung.ac.id',
            'password_hash' => Hash::make('multi123'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert([
            ['user_id' => $multiUserId, 'role_code' => 'MANAGER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $multiUserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $multiUserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('members')->insert([
            'id' => $multiMemberId,
            'user_id' => $multiUserId,
            'employee_number' => 'EMP-B6-MULTI',
            'member_number' => 'MEM-B6-MULTI',
            'full_name' => 'Multi Role User',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Login sebagai CASHIER
        $this->postJson('/api/v1/auth/login', [
            'username' => 'EMP-B6-MULTI',
            'password' => 'multi123',
            'role' => 'CASHIER',
        ])->assertOk()->assertJsonPath('role', 'CASHIER');

        // Boleh akses kasir, DILARANG akses endpoint MANAGER selama active_role = CASHIER
        $this->getJson('/api/v1/cashier/orders')->assertOk();
        $this->getJson('/api/v1/credit-policy')->assertStatus(403);
        $this->assertFalse(\App\Http\Middleware\RequireManager::allowed(request()));

        // 2. Beralih ke MEMBER -> kehilangan izin CASHIER dan MANAGER dalam sesi aktif yang sama
        $this->postJson('/api/v1/auth/switch-role', ['role' => 'MEMBER'])
            ->assertOk()
            ->assertJsonPath('active_role', 'MEMBER');

        $this->getJson('/api/v1/cashier/orders')->assertStatus(403);
        $this->getJson('/api/v1/credit-policy')->assertStatus(403);
        $this->withoutVite()->get('/cashier/pos')->assertRedirect(route('landing'));
        $this->withoutVite()->get('/manager/dashboard')->assertRedirect(route('landing'));
        $this->getJson('/api/v1/orders/my')->assertOk();

        // 3. Beralih kembali ke MANAGER -> RequireManager & endpoint manajer aktif kembali berdasarkan DB
        $this->postJson('/api/v1/auth/switch-role', ['role' => 'MANAGER'])
            ->assertOk()
            ->assertJsonPath('active_role', 'MANAGER');

        $this->getJson('/api/v1/credit-policy')->assertOk();
        $this->getJson('/api/v1/cashier/orders')->assertOk();

        // 4. Spoofing session active_role ke MANAGER oleh user MEMBER murni ditolak & dibersihkan
        $this->actingAs($this->memberUser)
            ->withSession(['active_role' => 'MANAGER'])
            ->getJson('/api/v1/credit-policy')
            ->assertStatus(403);
        $this->assertNull(session('active_role'));
    }
}
