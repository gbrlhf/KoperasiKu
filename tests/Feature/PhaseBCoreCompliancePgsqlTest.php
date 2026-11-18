<?php

namespace Tests\Feature;

use App\Models\CreditPolicy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseBCoreCompliancePgsqlTest extends TestCase
{
    use DatabaseTransactions;

    private User $memberUser;
    private User $otherMemberUser;
    private User $cashierUser;
    private User $cashierMemberUser;
    private User $managerUser;

    private string $memberId;
    private string $otherMemberId;
    private string $cashierMemberId;
    private string $productId;
    private string $noBalanceProductId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());

        foreach (['MEMBER', 'CASHIER', 'MANAGER'] as $role) {
            DB::table('roles')->updateOrInsert(['code' => $role], [
                'description' => "Role {$role}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $m1UserId = (string) Str::uuid();
        $m2UserId = (string) Str::uuid();
        $cUserId = (string) Str::uuid();
        $cmUserId = (string) Str::uuid();
        $mgrUserId = (string) Str::uuid();

        $this->memberId = (string) Str::uuid();
        $this->otherMemberId = (string) Str::uuid();
        $this->cashierMemberId = (string) Str::uuid();

        DB::table('users')->insert([
            [
                'id' => $m1UserId,
                'email' => 'ahmad.test@polman-bandung.ac.id',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $m2UserId,
                'email' => 'siti.test@polman-bandung.ac.id',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $cUserId,
                'email' => 'kasir.test@polman-bandung.ac.id',
                'password_hash' => Hash::make('kasir123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $cmUserId,
                'email' => 'kasirmember.test@polman-bandung.ac.id',
                'password_hash' => Hash::make('kasir123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $mgrUserId,
                'email' => 'manajer.test@polman-bandung.ac.id',
                'password_hash' => Hash::make('manajer123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('user_roles')->insert([
            ['user_id' => $m1UserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $m2UserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $cUserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $cmUserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $cmUserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $mgrUserId, 'role_code' => 'MANAGER', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('members')->insert([
            [
                'id' => $this->memberId,
                'user_id' => $m1UserId,
                'employee_number' => 'EMP-TST-01',
                'member_number' => 'MEM-TST-01',
                'full_name' => 'Ahmad Test',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->otherMemberId,
                'user_id' => $m2UserId,
                'employee_number' => 'EMP-TST-02',
                'member_number' => 'MEM-TST-02',
                'full_name' => 'Siti Test',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->cashierMemberId,
                'user_id' => $cmUserId,
                'employee_number' => 'EMP-TST-03',
                'member_number' => 'MEM-TST-03',
                'full_name' => 'Kasir Merangkap Anggota',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('member_credit_accounts')->insert([
            ['member_id' => $this->memberId, 'status' => 'ACTIVE', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['member_id' => $this->otherMemberId, 'status' => 'ACTIVE', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['member_id' => $this->cashierMemberId, 'status' => 'ACTIVE', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('credit_policies')->update(['active' => false]);
        CreditPolicy::create([
            'limit_amount' => 500000,
            'due_days' => 7,
            'active' => true,
            'effective_at' => now(),
        ]);

        $catId = (string) Str::uuid();
        DB::table('categories')->insert([
            'id' => $catId,
            'name' => 'Kategori B ' . Str::random(5),
            'slug' => 'cat-b-' . Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = (string) Str::uuid();
        $this->noBalanceProductId = (string) Str::uuid();

        DB::table('products')->insert([
            [
                'id' => $this->productId,
                'category_id' => $catId,
                'sku' => 'TST-BRS-01',
                'barcode' => '899000000001',
                'name' => 'Beras Uji 5kg',
                'selling_price' => 100000,
                'current_cost' => 80000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $this->noBalanceProductId,
                'category_id' => $catId,
                'sku' => 'TST-NOB-02',
                'barcode' => '899000000002',
                'name' => 'Produk Tanpa Saldo',
                'selling_price' => 25000,
                'current_cost' => 20000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('inventory_balances')->insert([
            'product_id' => $this->productId,
            'on_hand' => 10,
            'reserved' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->memberUser = User::findOrFail($m1UserId);
        $this->otherMemberUser = User::findOrFail($m2UserId);
        $this->cashierUser = User::findOrFail($cUserId);
        $this->cashierMemberUser = User::findOrFail($cmUserId);
        $this->managerUser = User::findOrFail($mgrUserId);
    }

    public function test_real_db_authentication_and_strict_rbac(): void
    {
        // Guest hit API harus 401 JSON
        $this->postJson('/api/v1/orders', [])->assertStatus(401);
        $this->getJson('/api/v1/credit-policy')->assertStatus(401);

        // Login gagal dengan sandi salah
        $this->postJson('/api/v1/auth/login', [
            'username' => 'EMP-TST-01',
            'password' => 'salah123',
        ])->assertStatus(401);

        // Member tidak boleh login sebagai MANAGER
        $this->postJson('/api/v1/auth/login', [
            'username' => 'EMP-TST-01',
            'password' => 'password123',
            'role' => 'MANAGER',
        ])->assertStatus(403);

        // Login valid dengan employee_number dan sandi DB
        $this->postJson('/api/v1/auth/login', [
            'username' => 'EMP-TST-01',
            'password' => 'password123',
            'role' => 'MEMBER',
        ])->assertOk()->assertJsonPath('role', 'MEMBER');

        // Member tidak dapat switch role ke CASHIER/MANAGER
        $this->post('/mock-switch-role', ['role' => 'CASHIER'])
            ->assertRedirect(route('landing'));

        // RBAC endpoint matrix
        $this->actingAs($this->memberUser)->postJson('/api/v1/pos/sales', [])->assertStatus(403);
        $this->actingAs($this->memberUser)->getJson('/api/v1/cashier/orders')->assertStatus(403);
        $this->actingAs($this->memberUser)->patchJson('/api/v1/credit-policy', ['limit_amount' => 100])->assertStatus(403);
        $this->actingAs($this->cashierUser)->patchJson('/api/v1/credit-policy', ['limit_amount' => 100])->assertStatus(403);
        $this->actingAs($this->cashierUser)->get('/manager/dashboard')->assertRedirect(route('landing'));
        $this->actingAs($this->managerUser)->getJson('/api/v1/credit-policy')->assertOk();
    }

    public function test_ownership_protection_and_anti_self_payment(): void
    {
        // Member tidak boleh menyisipkan member_id orang lain saat checkout
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'member_id' => $this->otherMemberId,
            'payment_method' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(403);

        // Cashier-Member buat pesanan pribadi
        $checkoutRes = $this->actingAs($this->cashierMemberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CREDIT',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(201);

        $orderId = $checkoutRes->json('data.order_id');

        // Member lain tidak melihat pesanan tersebut di /orders/my
        $myOrders = $this->actingAs($this->memberUser)->getJson('/api/v1/orders/my')->assertOk()->json('data');
        $this->assertEmpty($myOrders);

        // Kasir yg sama dilarang confirmPickup atas pesanannya sendiri (Anti self-pickup)
        $this->actingAs($this->cashierMemberUser)
            ->postJson("/api/v1/orders/{$orderId}/confirm-pickup", ['pickup_code' => $checkoutRes->json('data.pickup_code')])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'SELF_PICKUP_FORBIDDEN');

        // Kasir lain boleh confirmPickup
        $pickupRes = $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/orders/{$orderId}/confirm-pickup", ['pickup_code' => $checkoutRes->json('data.pickup_code')])
            ->assertOk();

        $receivableId = $pickupRes->json('data.receivable_id');
        $this->assertNotNull($receivableId);

        // Kasir-Member dilarang melakukan self-payment atas piutangnya sendiri
        $this->actingAs($this->cashierMemberUser)->postJson('/api/v1/receivables/payments', [
            'member_id' => $this->cashierMemberId,
            'method' => 'CASH',
            'amount' => 50000,
            'allocations' => [['receivable_id' => $receivableId, 'amount' => 50000]],
        ])->assertStatus(403)->assertJsonPath('error_code', 'SELF_PAYMENT_FORBIDDEN');

        // Kasir-Member dilarang POS kredit atas akunnya sendiri
        $this->actingAs($this->cashierMemberUser)->postJson('/api/v1/pos/sales', [
            'member_id' => $this->cashierMemberId,
            'payment_choice' => 'CREDIT',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(403)->assertJsonPath('error_code', 'SELF_PAYMENT_FORBIDDEN');
    }

    public function test_cash_and_transfer_order_payment_verification_and_hpp_snapshot_at_handover(): void
    {
        $checkoutRes = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'BANK_TRANSFER',
            'idempotency_key' => 'chk-trf-001',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201);

        $orderId = $checkoutRes->json('data.order_id');
        $this->assertEquals('PENDING_PAYMENT', $checkoutRes->json('data.status'));

        // Cek stok: on_hand tetap 10, reserved jadi 2, available 8
        $inv = DB::table('inventory_balances')->where('product_id', $this->productId)->first();
        $this->assertEquals(10, $inv->on_hand);
        $this->assertEquals(2, $inv->reserved);

        // Idempotent retry tidak menduplikasi order atau reservasi
        $retry = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'BANK_TRANSFER',
            'idempotency_key' => 'chk-trf-001',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201);
        $this->assertEquals($orderId, $retry->json('data.order_id'));
        $this->assertEquals(2, DB::table('inventory_balances')->where('product_id', $this->productId)->value('reserved'));

        // Pickup sebelum pembayaran terverifikasi harus ditolak
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/orders/{$orderId}/confirm-pickup")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PAYMENT_NOT_VERIFIED');

        // Catat transfer PENDING_VERIFICATION
        $payRes = $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'BANK_TRANSFER',
            'amount' => 200000,
            'external_reference' => 'TRX-BCA-UNIQ-1',
            'idempotency_key' => 'pay-ord-001',
        ])->assertStatus(201);
        $paymentId = $payRes->json('data.payment_id');
        $this->assertEquals('PENDING_VERIFICATION', $payRes->json('data.status'));

        // Duplikasi external_reference wajib ditolak
        $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'BANK_TRANSFER',
            'amount' => 200000,
            'external_reference' => 'TRX-BCA-UNIQ-1',
        ])->assertStatus(422)->assertJsonPath('error_code', 'DUPLICATE_PAYMENT');

        // Simulasikan HPP produk naik sebelum serah terima (mis. karena purchase receipt masuk)
        DB::table('products')->where('id', $this->productId)->update(['current_cost' => 85000]);

        // Verifikasi transfer -> order menjadi READY_FOR_PICKUP
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/payments/{$paymentId}/verify", ['action' => 'VERIFY'])
            ->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED');

        $this->assertEquals('READY_FOR_PICKUP', DB::table('orders')->where('id', $orderId)->value('status'));

        // Confirm pickup dengan idempotency key
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/orders/{$orderId}/confirm-pickup", ['idempotency_key' => 'pkp-idem-001'])
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED');

        // Panggilan ulang dengan idempotency_key yang sama aman & tidak mengurangi stok 2x
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/orders/{$orderId}/confirm-pickup", ['idempotency_key' => 'pkp-idem-001'])
            ->assertOk()
            ->assertJsonPath('data.idempotent', true);

        $invAfter = DB::table('inventory_balances')->where('product_id', $this->productId)->first();
        $this->assertEquals(8, $invAfter->on_hand);
        $this->assertEquals(0, $invAfter->reserved);

        // HPP di-snapshot saat COMPLETED (85000), harga jual tetap snapshot saat checkout (100000)
        $orderItem = DB::table('order_items')->where('order_id', $orderId)->first();
        $this->assertEquals(100000.0, (float) $orderItem->unit_price_snapshot);
        $this->assertEquals(85000.0, (float) $orderItem->hpp_snapshot);
    }

    public function test_receivable_transfer_pending_then_verify_allocates_without_changing_due_date(): void
    {
        // Buat order kredit 200.000 & serahkan barang
        $checkoutRes = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CREDIT',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201);

        $orderId = $checkoutRes->json('data.order_id');
        $pickupRes = $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/orders/{$orderId}/confirm-pickup")
            ->assertOk();

        $receivableId = $pickupRes->json('data.receivable_id');
        $originalDueDate = $pickupRes->json('data.due_date');
        $expectedDue = CarbonImmutable::now('Asia/Jakarta')->addDays(7)->toDateString();
        $this->assertSame($expectedDue, $originalDueDate);

        // Bayar cicilan Rp 75.000 via BANK_TRANSFER -> saldo TIDAK boleh berkurang sebelum verifikasi
        $payRes = $this->actingAs($this->cashierUser)->postJson('/api/v1/receivables/payments', [
            'member_id' => $this->memberId,
            'method' => 'BANK_TRANSFER',
            'amount' => 75000,
            'external_reference' => 'TRF-CICIL-01',
            'idempotency_key' => 'cicil-idem-01',
            'allocations' => [['receivable_id' => $receivableId, 'amount' => 75000]],
        ])->assertOk();

        $paymentId = $payRes->json('data.payment_id');
        $this->assertEquals('PENDING_VERIFICATION', $payRes->json('data.status'));
        $this->assertEquals(200000.0, (float) DB::table('receivables')->where('id', $receivableId)->value('outstanding_amount'));
        $this->assertEquals(0, DB::table('receivable_payment_allocations')->where('receivable_id', $receivableId)->count());

        // Verifikasi transfer -> saldo berkurang menjadi 125.000, status PARTIALLY_PAID, due_date tidak berubah!
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/payments/{$paymentId}/verify", ['action' => 'VERIFY'])
            ->assertOk()
            ->assertJsonPath('data.status', 'VERIFIED');

        // Panggil verify kedua kali harus idempotent (tidak memotong saldo dua kali)
        $this->actingAs($this->cashierUser)
            ->postJson("/api/v1/payments/{$paymentId}/verify", ['action' => 'VERIFY'])
            ->assertOk()
            ->assertJsonPath('data.idempotent', true);

        $recAfter = DB::table('receivables')->where('id', $receivableId)->first();
        $this->assertEquals(125000.0, (float) $recAfter->outstanding_amount);
        $this->assertEquals('PARTIALLY_PAID', $recAfter->status);
        $this->assertEquals($originalDueDate, $recAfter->due_date);
        $this->assertEquals(1, DB::table('receivable_payment_allocations')->where('payment_id', $paymentId)->count());
    }

    public function test_pos_respects_available_stock_and_forbids_non_member_credit_and_missing_balance(): void
    {
        // Member reservasi 8 dari 10 -> available = 2
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 8]],
        ])->assertStatus(201);

        // POS coba beli 3 -> harus ditolak karena available hanya 2 (walaupun on_hand masih 10)
        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 3]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'INSUFFICIENT_STOCK');

        // POS beli 2 -> berhasil
        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 2]],
        ])->assertStatus(201);

        // Produk tanpa inventory_balance TIDAK boleh auto-seed on_hand=50
        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CASH',
            'items' => [['product_id' => $this->noBalanceProductId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'INSUFFICIENT_STOCK');
        $this->assertDatabaseMissing('inventory_balances', ['product_id' => $this->noBalanceProductId]);

        // Non-member tidak boleh kredit di POS
        $this->actingAs($this->cashierUser)->postJson('/api/v1/pos/sales', [
            'payment_choice' => 'CREDIT',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'CREDIT_NOT_ELIGIBLE');
    }

    public function test_overdue_blocks_credit_only_cancel_releases_hold_and_manager_policy_logs_written(): void
    {
        // 1. Overdue memblokir CREDIT tetapi mengizinkan CASH
        $oldOrderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $oldOrderId,
            'member_id' => $this->memberId,
            'channel' => 'ONLINE_PICKUP',
            'status' => 'COMPLETED',
            'payment_choice' => 'CREDIT',
            'total_amount' => 50000,
            'completed_at' => CarbonImmutable::now('UTC')->subDays(8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('receivables')->insert([
            'id' => (string) Str::uuid(),
            'order_id' => $oldOrderId,
            'member_id' => $this->memberId,
            'original_amount' => 50000,
            'outstanding_amount' => 50000,
            'due_date' => CarbonImmutable::now('Asia/Jakarta')->subDay()->toDateString(),
            'status' => 'OPEN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CREDIT',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'OVERDUE_RECEIVABLE');

        $cashOrder = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(201);

        // Cancel order melepaskan reservasi stok & tidak membuat piutang
        $this->actingAs($this->cashierUser)
            ->postJson('/api/v1/orders/' . $cashOrder->json('data.order_id') . '/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');
        $this->assertEquals(0, DB::table('inventory_balances')->where('product_id', $this->productId)->value('reserved'));

        // Manager update global credit policy + menulis credit_policy_logs & audit_logs
        $this->actingAs($this->managerUser)->patchJson('/api/v1/credit-policy', [
            'limit_amount' => 750000,
            'reason' => 'Penyesuaian limit semester',
        ])->assertOk()->assertJsonPath('data.due_days', 7);

        $this->assertDatabaseHas('credit_policy_logs', ['new_limit_amount' => 750000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CREDIT_POLICY_UPDATED']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ORDER_CANCELLED']);
    }

    public function test_transaction_rollback_on_failure(): void
    {
        $beforeOrders = DB::table('orders')->count();
        $beforeReserved = (int) DB::table('inventory_balances')->where('product_id', $this->productId)->value('reserved');

        // Keranjang berisi 1 item valid dan 1 item tanpa saldo stok -> seluruh transaksi wajib rollback
        $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CREDIT',
            'items' => [
                ['product_id' => $this->productId, 'quantity' => 2],
                ['product_id' => $this->noBalanceProductId, 'quantity' => 1],
            ],
        ])->assertStatus(422)->assertJsonPath('error_code', 'INSUFFICIENT_STOCK');

        // Pastikan tidak ada partial write
        $this->assertSame($beforeOrders, DB::table('orders')->count());
        $this->assertSame($beforeReserved, (int) DB::table('inventory_balances')->where('product_id', $this->productId)->value('reserved'));
        $this->assertEquals(0, DB::table('credit_holds')->where('member_id', $this->memberId)->count());
    }

    public function test_error_response_sanitization_hides_internal_sql_details(): void
    {
        // Simulasikan unexpected internal error pada TransactionService -> API tidak boleh membocorkan SQLSTATE/detail internal
        $this->mock(\App\Services\TransactionService::class, function ($mock) {
            $mock->shouldReceive('checkout')->once()->andThrow(new \RuntimeException('SQLSTATE[42P01]: internal secret query failure'));
        });

        $errRes = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(500)->assertJsonPath('error_code', 'INTERNAL_ERROR');

        $this->assertStringNotContainsString('SQLSTATE', (string) $errRes->json('message'));
        $this->assertStringNotContainsString('secret', (string) $errRes->json('message'));
    }

    public function test_frontend_baseline_pages_render_without_regression(): void
    {
        $this->withoutVite()->get('/')->assertOk();
        $this->withoutVite()->get('/login')->assertOk();
        $this->withoutVite()->actingAs($this->cashierUser)->get('/cashier/pos')
            ->assertOk()
            ->assertSee('Keranjang Meja Kasir')
            ->assertSee('tokped-product-card');
        $this->withoutVite()->actingAs($this->managerUser)->get('/manager/dashboard')
            ->assertOk()
            ->assertSee('manager-v2');
    }
}
