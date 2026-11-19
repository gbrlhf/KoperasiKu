<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['MEMBER' => 'Anggota', 'CASHIER' => 'Kasir', 'MANAGER' => 'Manajer'] as $code => $description) {
            Role::firstOrCreate(['code' => $code], ['description' => $description]);
        }
    }
}
