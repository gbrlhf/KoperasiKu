<?php

namespace Tests\Feature;

use App\Contracts\InventoryServiceInterface;
use App\Exceptions\BusinessRuleException;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseB7HppAndPicCCompatPgsqlTest extends TestCase
{
    use DatabaseTransactions;

    private static bool $migrated = false;

    private User $memberUser;
    private User $cashierUser;
    private string $memberId;
    private string $categoryId;
    private string $productId;

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

        $memberUserId = (string) Str::uuid();
        $cashierUserId = (string) Str::uuid();
        $this->memberId = (string) Str::uuid();

        DB::table('users')->insert([
            [
                'id' => $memberUserId,
                'email' => 'b7.member.' . Str::random(4) . '@polman-bandung.ac.id',
                'password_hash' => Hash::make('password123'),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $cashierUserId,
                'email' => 'b7.cashier.' . Str::random(4) . '@polman-bandung.ac.id',
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
            'employee_number' => 'EMP-B7-' . Str::random(4),
            'member_number' => 'MEM-B7-' . Str::random(4),
            'full_name' => 'Anggota B7',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->categoryId = (string) Str::uuid();
        DB::table('categories')->insert([
            'id' => $this->categoryId,
            'name' => 'Kat B7 ' . Str::random(5),
            'slug' => 'kat-b7-' . Str::random(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = (string) Str::uuid();
        DB::table('products')->insert([
            'id' => $this->productId,
            'category_id' => $this->categoryId,
            'sku' => 'B7-SKU-' . Str::random(4),
            'barcode' => '8997000' . random_int(10000, 99999),
            'name' => 'Beras B7 Test',
            'selling_price' => 80000,
            'current_cost' => 60000,
            'reorder_point' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_balances')->insert([
            'product_id' => $this->productId,
            'on_hand' => 10,
            'reserved' => 4, // available = 6
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->memberUser = User::findOrFail($memberUserId);
        $this->cashierUser = User::findOrFail($cashierUserId);
    }

    public function test_b7_1_inventory_contract_receive_and_available_queries_respect_reserved(): void
    {
        $inventory = app(InventoryServiceInterface::class);

        $this->assertSame(10, $inventory->getOnHand($this->productId));
        $this->assertSame(6, $inventory->getAvailable($this->productId));

        $receiptId = (string) Str::uuid();
        DB::table('purchase_receipts')->insert([
            'id' => $receiptId,
            'receipt_number' => 'RCV-B7-' . Str::random(5),
            'received_at' => now(),
            'status' => 'COMPLETED',
            'created_by' => $this->cashierUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventory->receive($this->productId, 5, 'PURCHASE_RECEIPT', (string) $this->cashierUser->id, $receiptId);

        // on_hand bertambah 5 (10->15), reserved tetap 4, available menjadi 11
        $this->assertSame(15, $inventory->getOnHand($this->productId));
        $this->assertSame(11, $inventory->getAvailable($this->productId));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->productId,
            'quantity_change' => 5,
            'type' => 'PURCHASE_RECEIPT',
            'reference_id' => $receiptId,
        ]);

        // Kuantitas <= 0 wajib ditolak
        $this->expectException(BusinessRuleException::class);
        $inventory->receive($this->productId, 0, 'PURCHASE_RECEIPT', (string) $this->cashierUser->id, $receiptId);
    }

    public function test_b7_2_completed_order_hpp_snapshot_is_immutable_at_model_and_postgresql_level(): void
    {
        // Checkout saat current_cost = 60000 -> hpp_snapshot masih NULL
        $checkout = $this->actingAs($this->memberUser)->postJson('/api/v1/orders', [
            'payment_method' => 'CASH',
            'items' => [['product_id' => $this->productId, 'quantity' => 1]],
        ])->assertStatus(201);

        $orderId = $checkout->json('data.order_id');
        $this->assertNull(DB::table('order_items')->where('order_id', $orderId)->value('hpp_snapshot'));

        // Catat pembayaran lunas & ubah current_cost menjadi 65000 sebelum serah terima
        $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$orderId}/payments", [
            'method' => 'CASH',
            'amount' => 80000,
        ])->assertStatus(201);

        DB::table('products')->where('id', $this->productId)->update(['current_cost' => 65000]);

        // Confirm pickup -> hpp_snapshot final terkunci pada 65000 & order menjadi COMPLETED
        $this->actingAs($this->cashierUser)->postJson("/api/v1/orders/{$orderId}/confirm-pickup", ['pickup_code' => $checkout->json('data.pickup_code'), 'handover_confirmed' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED');

        $orderItem = OrderItem::where('order_id', $orderId)->firstOrFail();
        $this->assertEquals(65000.0, (float) $orderItem->hpp_snapshot);

        // 1. Update via Eloquent harus ditolak dengan BusinessRuleException IMMUTABLE_HPP_SNAPSHOT
        $eloquentBlocked = false;
        try {
            $orderItem->update(['hpp_snapshot' => 99999]);
        } catch (BusinessRuleException $e) {
            $eloquentBlocked = ($e->errorCode === 'IMMUTABLE_HPP_SNAPSHOT');
        }
        $this->assertTrue($eloquentBlocked, 'OrderItem Eloquent wajib menolak perubahan hpp_snapshot setelah COMPLETED.');

        // 2. Update langsung via SQL / DB::table harus ditolak oleh trigger PostgreSQL trg_protect_completed_order_item_hpp
        $dbTriggerBlocked = false;
        try {
            DB::transaction(function () use ($orderItem): void {
                DB::table('order_items')->where('id', $orderItem->id)->update(['hpp_snapshot' => 99999]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $dbTriggerBlocked = str_contains($e->getMessage(), 'IMMUTABLE_HPP_SNAPSHOT');
        }
        $this->assertTrue($dbTriggerBlocked, 'Trigger PostgreSQL wajib menolak update langsung hpp_snapshot pada order COMPLETED.');
        $this->assertEquals(65000.0, (float) DB::table('order_items')->where('id', $orderItem->id)->value('hpp_snapshot'));
    }

    public function test_b7_3_pic_c_additive_schema_and_nullable_compatibility(): void
    {
        foreach ([
            'suppliers',
            'product_price_histories',
            'purchase_receipts',
            'receipt_items',
            'operating_expenses',
            'import_batches',
            'import_rows',
            'import_errors',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel PIC C {$table} harus tersedia.");
        }

        $this->assertTrue(Schema::hasColumn('categories', 'description'));
        $this->assertTrue(Schema::hasColumn('products', 'supplier_id'));
        $this->assertTrue(Schema::hasColumn('products', 'reorder_point'));
        $this->assertTrue(Schema::hasColumn('products', 'image_path'));

        // Pastikan kategori model PIC C (tanpa slug) dapat di-insert tanpa menabrak constraint NOT NULL
        $catNoSlugId = (string) Str::uuid();
        DB::table('categories')->insert([
            'id' => $catNoSlugId,
            'name' => 'Kategori Tanpa Slug ' . Str::random(4),
            'description' => 'Kompatibel PIC C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('categories', ['id' => $catNoSlugId]);

        // Pastikan legacy member tanpa employee_number & historical NULL hpp_snapshot tetap didukung
        $legacyMemberId = (string) Str::uuid();
        DB::table('members')->insert([
            'id' => $legacyMemberId,
            'user_id' => null,
            'employee_number' => null,
            'member_number' => null,
            'full_name' => 'Anggota Historis',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertDatabaseHas('members', ['id' => $legacyMemberId]);
    }
}
