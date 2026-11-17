<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\InventoryBalance;
use App\Models\CreditPolicy;
use App\Models\MemberCreditAccount;

class TransactionTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat User Anggota Dummy
        $memberId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $memberId,
            'email' => 'anggota@koperasiku.test',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Buat Kebijakan Kredit Global (Limit & Jatuh Tempo)
        CreditPolicy::create([
            'limit_amount' => 1000000,
            'due_days' => 7,
            'effective_at' => now(),
        ]);

        // 3. Buat Akun Kredit Anggota (Hanya kolom yang ada di migrasi: member_id, status, activated_at)
        MemberCreditAccount::create([
            'member_id' => $memberId,
            'status' => 'ACTIVE',
            'activated_at' => now(),
        ]);

        // 4. Buat Stok Produk Dummy di Inventory
        $productId = (string) Str::uuid();
        InventoryBalance::create([
            'product_id' => $productId,
            'on_hand' => 50,
            'reserved' => 0,
        ]);

        $this->command->info("==========================================");
        $this->command->info("Seeder berhasil dijalankan!");
        $this->command->info("Member ID  : {$memberId}");
        $this->command->info("Product ID : {$productId}");
        $this->command->info("==========================================");
    }
}