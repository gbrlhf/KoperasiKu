<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionE2ETest extends TestCase
{
    use DatabaseTransactions;

    private User $memberUser;
    private User $cashierUser;
    private string $memberId;

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

        $memberUserId = (string) Str::uuid();
        $cashierUserId = (string) Str::uuid();
        $this->memberId = (string) Str::uuid();

        DB::table('users')->insert([
            [
                'id' => $memberUserId,
                'email' => 'member.e2e@polman-bandung.ac.id',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $cashierUserId,
                'email' => 'cashier.e2e@polman-bandung.ac.id',
                'password_hash' => Hash::make('kasir123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('user_roles')->insert([
            ['user_id' => $memberUserId, 'role_code' => 'MEMBER', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $cashierUserId, 'role_code' => 'CASHIER', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('members')->insert([
            'id' => $this->memberId,
            'user_id' => $memberUserId,
            'employee_number' => 'EMP-E2E-1',
            'member_number' => 'MEM-E2E-1',
            'full_name' => 'Ahmad E2E',
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

        DB::table('credit_policies')->update(['active' => false]);
        DB::table('credit_policies')->insert([
            'id' => (string) Str::uuid(),
            'limit_amount' => 1000000,
            'due_days' => 7,
            'active' => true,
            'effective_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = (string) Str::uuid();
        DB::table('categories')->insert([
            'id' => $catId,
            'name' => 'Sembako E2E ' . Str::random(4),
            'slug' => 'sembako-e2e-' . Str::random(4),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $products = [
            ['sku' => 'SEM-001', 'barcode' => '899100100001', 'name' => 'Beras Premium Ramos 5 Kg', 'price' => 74500, 'cost' => 68000, 'stock' => 40],
            ['sku' => 'MNY-001', 'barcode' => '899100100002', 'name' => 'Minyak Goreng Pouch 2 Liter', 'price' => 36000, 'cost' => 32000, 'stock' => 40],
            ['sku' => 'SSU-001', 'barcode' => '899100100004', 'name' => 'Susu UHT Full Cream 1 Liter', 'price' => 19500, 'cost' => 17000, 'stock' => 40],
            ['sku' => 'TLR-001', 'barcode' => '899100100005', 'name' => 'Telur Ayam Negeri Fresh 1 Kg', 'price' => 28500, 'cost' => 25000, 'stock' => 40],
        ];

        foreach ($products as $p) {
            $existing = DB::table('products')->where('sku', $p['sku'])->first();
            $pid = $existing?->id ?? (string) Str::uuid();
            if (!$existing) {
                DB::table('products')->insert([
                    'id' => $pid,
                    'category_id' => $catId,
                    'sku' => $p['sku'],
                    'barcode' => $p['barcode'],
                    'name' => $p['name'],
                    'selling_price' => $p['price'],
                    'current_cost' => $p['cost'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('inventory_balances')->updateOrInsert(['product_id' => $pid], [
                'on_hand' => $p['stock'],
                'reserved' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->memberUser = User::findOrFail($memberUserId);
        $this->cashierUser = User::findOrFail($cashierUserId);
    }

    public function test_checkout_endpoint_creates_order_and_pickup_code(): void
    {
        $response = $this->actingAs($this->memberUser)->postJson('/checkout', [
            'payment_choice' => 'CREDIT',
            'items' => [
                ['product_id' => 'SEM-001', 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => ['order_id', 'order_no', 'pickup_code', 'status', 'payment_choice', 'total_amount']
            ]);

        $orderId = $response->json('data.order_id');
        $pickupCode = $response->json('data.pickup_code');
        $this->assertStringStartsWith('PKP-', $pickupCode);
        // Sebelum pickup, hpp_snapshot masih NULL
        $this->assertNull(DB::table('order_items')->where('order_id', $orderId)->value('hpp_snapshot'));

        // Konfirmasi pickup oleh kasir berbeda
        $pickupResponse = $this->actingAs($this->cashierUser)->postJson('/cashier/confirm-pickup', [
            'pickup_code' => $pickupCode,
        ]);

        $pickupResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'COMPLETED']
            ]);

        // Setelah pickup selesai, hpp_snapshot terisi final
        $this->assertEquals(68000.0, (float) DB::table('order_items')->where('order_id', $orderId)->value('hpp_snapshot'));
    }

    public function test_checkout_with_storefront_prd_code_like_prd_005(): void
    {
        $response = $this->actingAs($this->memberUser)->postJson('/checkout', [
            'payment_choice' => 'CASH',
            'items' => [
                ['product_id' => 'PRD-005', 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertEquals(28500, (float) $response->json('data.total_amount'));
    }

    public function test_checkout_with_sku_ssu_001(): void
    {
        $response = $this->actingAs($this->memberUser)->postJson('/checkout', [
            'payment_choice' => 'CASH',
            'items' => [
                ['product_id' => 'SSU-001', 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertEquals(39000, (float) $response->json('data.total_amount'));
        $this->assertNotEmpty($response->json('data.pickup_code'));
    }

    public function test_pos_direct_sale_endpoint(): void
    {
        $response = $this->actingAs($this->cashierUser)->postJson('/cashier/pos-sale', [
            'payment_choice' => 'CASH',
            'items' => [
                ['product_id' => 'MNY-001', 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'COMPLETED',
                    'channel' => 'POS',
                ]
            ]);
    }

    public function test_product_barcode_lookup_endpoint(): void
    {
        $response = $this->actingAs($this->cashierUser)->getJson('/api/v1/products/lookup?barcode=899100100001');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'sku' => 'SEM-001',
                    'barcode' => '899100100001',
                    'name' => 'Beras Premium Ramos 5 Kg',
                ]
            ]);
    }
}
