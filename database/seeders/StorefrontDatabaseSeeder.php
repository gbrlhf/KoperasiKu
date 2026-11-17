<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CreditPolicy;
use App\Models\InventoryBalance;
use App\Models\MemberCreditAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\StockReservation;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StorefrontDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles Master (PRD v1.4: Tepat 3 role)
        $roles = [
            ['code' => 'MEMBER', 'description' => 'Anggota Koperasi Pegawai POLMAN Bandung'],
            ['code' => 'CASHIER', 'description' => 'Petugas Kasir Gerai Kanayakan A11'],
            ['code' => 'MANAGER', 'description' => 'Manajer Operasional & Analitik Koperasi'],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['code' => $r['code']], [
                'description' => $r['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. User & Akun Anggota Utama (Ahmad Fauzi / EMP-001)
        $memberId = '00000000-0000-0000-0000-000000000001';
        DB::table('users')->updateOrInsert(['id' => $memberId], [
            'email' => 'ahmad.fauzi@polman-bandung.ac.id',
            'password_hash' => Hash::make('password123'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->updateOrInsert(['user_id' => $memberId, 'role_code' => 'MEMBER'], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User Kasir
        $cashierId = '00000000-0000-0000-0000-000000000002';
        DB::table('users')->updateOrInsert(['id' => $cashierId], [
            'email' => 'kasir@polman-bandung.ac.id',
            'password_hash' => Hash::make('kasir123'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->updateOrInsert(['user_id' => $cashierId, 'role_code' => 'CASHIER'], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User Manajer
        $managerId = '00000000-0000-0000-0000-000000000003';
        DB::table('users')->updateOrInsert(['id' => $managerId], [
            'email' => 'manajer@polman-bandung.ac.id',
            'password_hash' => Hash::make('manajer123'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->updateOrInsert(['user_id' => $managerId, 'role_code' => 'MANAGER'], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Kebijakan Limit Kredit Global (Limit Rp 1.000.000 & Tempo 7 Hari Bunga 0%)
        CreditPolicy::updateOrCreate(
            ['active' => true],
            [
                'limit_amount' => 1000000,
                'due_days' => 7,
                'effective_at' => now(),
            ]
        );

        // 4. Akun Kredit Anggota (Aktif)
        MemberCreditAccount::updateOrCreate(
            ['member_id' => $memberId],
            [
                'status' => 'ACTIVE',
                'activated_at' => now(),
            ]
        );

        // 5. Kategori Master
        $categoriesData = [
            ['name' => 'Sembako & Beras', 'slug' => 'sembako-beras', 'icon' => 'bi-basket2'],
            ['name' => 'Minyak & Bumbu', 'slug' => 'minyak-bumbu', 'icon' => 'bi-droplet-half'],
            ['name' => 'Susu & Minuman', 'slug' => 'susu-minuman', 'icon' => 'bi-cup-hot'],
            ['name' => 'Snack & Biskuit', 'slug' => 'snack-biskuit', 'icon' => 'bi-box2-heart'],
            ['name' => 'Sabun & Cuci', 'slug' => 'sabun-cuci', 'icon' => 'bi-stars'],
            ['name' => 'Kebutuhan Kantor', 'slug' => 'kebutuhan-kantor', 'icon' => 'bi-pencil-square'],
            ['name' => 'Produk Segar', 'slug' => 'produk-segar', 'icon' => 'bi-egg'],
            ['name' => 'Olahan Anggota', 'slug' => 'olahan-anggota', 'icon' => 'bi-award'],
        ];

        foreach ($categoriesData as $c) {
            Category::updateOrCreate(['slug' => $c['slug']], [
                'name' => $c['name'],
                'icon' => $c['icon'],
            ]);
        }

        // 6. Master 20 Produk Komoditas Koperasi (Sesuai Etalase Storefront & POS)
        $productsData = [
            [
                'id' => '00000000-0000-0000-0001-000000000001',
                'sku' => 'SEM-001',
                'barcode' => '899100100001',
                'name' => 'Beras Premium Ramos 5 Kg',
                'category' => 'Sembako & Beras',
                'price' => 74500,
                'original_price' => 82000,
                'cost' => 65000,
                'unit' => 'Sak',
                'badge' => 'Terlaris',
                'stock' => 32,
                'image' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 180,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000002',
                'sku' => 'MNY-001',
                'barcode' => '899100100002',
                'name' => 'Minyak Goreng Pouch 2 Liter',
                'category' => 'Minyak & Bumbu',
                'price' => 36000,
                'original_price' => 39500,
                'cost' => 31000,
                'unit' => 'Pouch',
                'badge' => 'Diskon 9%',
                'stock' => 24,
                'image' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 240,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000003',
                'sku' => 'GUL-001',
                'barcode' => '899100100003',
                'name' => 'Gula Pasir Kristal Putih 1 Kg',
                'category' => 'Sembako & Beras',
                'price' => 17500,
                'original_price' => 19000,
                'cost' => 15000,
                'unit' => 'Kg',
                'badge' => 'Promo',
                'stock' => 40,
                'image' => 'https://images.unsplash.com/photo-1581441363689-1f3c3c414635?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 150,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000004',
                'sku' => 'SSU-001',
                'barcode' => '899100100004',
                'name' => 'Susu UHT Full Cream 1 Liter',
                'category' => 'Susu & Minuman',
                'price' => 19500,
                'original_price' => 22000,
                'cost' => 16500,
                'unit' => 'Kotak',
                'badge' => 'Favorit',
                'stock' => 18,
                'image' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.7,
                'sales' => 95,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000005',
                'sku' => 'TLR-001',
                'barcode' => '899100100005',
                'name' => 'Telur Ayam Negeri Fresh 1 Kg',
                'category' => 'Produk Segar',
                'price' => 28500,
                'original_price' => 31000,
                'cost' => 25000,
                'unit' => 'Kg',
                'badge' => 'Segar',
                'stock' => 15,
                'image' => 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 210,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000006',
                'sku' => 'TPG-001',
                'barcode' => '899100100006',
                'name' => 'Tepung Terigu Serbaguna 1 Kg',
                'category' => 'Sembako & Beras',
                'price' => 12500,
                'original_price' => 14000,
                'cost' => 10500,
                'unit' => 'Kg',
                'badge' => 'Protein Sedang',
                'stock' => 30,
                'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 160,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000007',
                'sku' => 'KCP-001',
                'barcode' => '899100100007',
                'name' => 'Kecap Manis Refill 550 ml',
                'category' => 'Minyak & Bumbu',
                'price' => 22000,
                'original_price' => 24500,
                'cost' => 18500,
                'unit' => 'Pouch',
                'badge' => 'Kedelai Hitam',
                'stock' => 20,
                'image' => 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 140,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000008',
                'sku' => 'SRD-001',
                'barcode' => '899100100008',
                'name' => 'Sarden Kaleng Saus Tomat 425 gr',
                'category' => 'Sembako & Beras',
                'price' => 24500,
                'original_price' => 27000,
                'cost' => 20500,
                'unit' => 'Kaleng',
                'badge' => 'Praktis',
                'stock' => 16,
                'image' => 'https://images.unsplash.com/photo-1534483509719-3feaee7c30da?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 115,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000009',
                'sku' => 'GRM-001',
                'barcode' => '899100100009',
                'name' => 'Garam Beryodium Halus 500 gr',
                'category' => 'Minyak & Bumbu',
                'price' => 6500,
                'original_price' => 8000,
                'cost' => 5000,
                'unit' => 'Bks',
                'badge' => 'Hemat',
                'stock' => 50,
                'image' => 'https://images.unsplash.com/photo-1518110925495-5fe2fda0442c?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 290,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000010',
                'sku' => 'MRG-001',
                'barcode' => '899100100010',
                'name' => 'Margarin Serbaguna 200 gr',
                'category' => 'Minyak & Bumbu',
                'price' => 9500,
                'original_price' => 11000,
                'cost' => 7800,
                'unit' => 'Sachet',
                'badge' => 'Serbaguna',
                'stock' => 35,
                'image' => 'https://images.unsplash.com/photo-1589985270826-4b7bb135bc9d?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 175,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000011',
                'sku' => 'SBN-001',
                'barcode' => '899100100011',
                'name' => 'Deterjen Bubuk Konsentrat 800 gr',
                'category' => 'Sabun & Cuci',
                'price' => 21000,
                'original_price' => 23500,
                'cost' => 17000,
                'unit' => 'Bks',
                'badge' => 'Anti Bakteri',
                'stock' => 28,
                'image' => 'https://images.unsplash.com/photo-1610557892470-55d9e80c0bce?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 120,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000012',
                'sku' => 'PRG-001',
                'barcode' => '899100100012',
                'name' => 'Sabun Cuci Piring Jeruk Nipis 750 ml',
                'category' => 'Sabun & Cuci',
                'price' => 14000,
                'original_price' => 16000,
                'cost' => 11500,
                'unit' => 'Pouch',
                'badge' => 'Ekstrak Jeruk',
                'stock' => 32,
                'image' => 'https://images.unsplash.com/photo-1585421514738-01798e348b17?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 210,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000013',
                'sku' => 'LNT-001',
                'barcode' => '899100100013',
                'name' => 'Pembersih Lantai Aroma Pinus 750 ml',
                'category' => 'Sabun & Cuci',
                'price' => 13500,
                'original_price' => 15000,
                'cost' => 10800,
                'unit' => 'Pouch',
                'badge' => 'Wangi Tahan Lama',
                'stock' => 25,
                'image' => 'https://images.unsplash.com/photo-1528740561666-dc2479dc08ab?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.7,
                'sales' => 88,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000014',
                'sku' => 'TIS-001',
                'barcode' => '899100100014',
                'name' => 'Tisu Dapur Roll Higienis 2 Ply',
                'category' => 'Sabun & Cuci',
                'price' => 16000,
                'original_price' => 18500,
                'cost' => 13000,
                'unit' => 'Roll',
                'badge' => 'Serap Minyak',
                'stock' => 20,
                'image' => 'https://images.unsplash.com/photo-1584556812952-905ffd0c611a?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 95,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000015',
                'sku' => 'SBN-002',
                'barcode' => '899100100015',
                'name' => 'Sabun Mandi Cair Refill 450 ml',
                'category' => 'Sabun & Cuci',
                'price' => 23000,
                'original_price' => 26000,
                'cost' => 18800,
                'unit' => 'Pouch',
                'badge' => 'Moisturizer',
                'stock' => 22,
                'image' => 'https://images.unsplash.com/photo-1608248597359-5f214227f428?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 135,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000016',
                'sku' => 'KPI-001',
                'barcode' => '899100100016',
                'name' => 'Kopi Bubuk Arabika Robusta 250 gr',
                'category' => 'Susu & Minuman',
                'price' => 32000,
                'original_price' => 36000,
                'cost' => 26000,
                'unit' => 'Bks',
                'badge' => 'Aroma Mantap',
                'stock' => 18,
                'image' => 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 110,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000017',
                'sku' => 'TEH-001',
                'barcode' => '899100100017',
                'name' => 'Teh Celup Hitam Premium (Isi 50)',
                'category' => 'Susu & Minuman',
                'price' => 16500,
                'original_price' => 18500,
                'cost' => 13500,
                'unit' => 'Kotak',
                'badge' => 'Pekat & Segar',
                'stock' => 30,
                'image' => 'https://images.unsplash.com/photo-1597481499750-3e6b22637e12?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 130,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000018',
                'sku' => 'BSK-001',
                'barcode' => '899100100018',
                'name' => 'Biskuit Gandum Cokelat 150 gr',
                'category' => 'Snack & Biskuit',
                'price' => 11000,
                'original_price' => 12500,
                'cost' => 8800,
                'unit' => 'Bks',
                'badge' => 'Tinggi Serat',
                'stock' => 25,
                'image' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.7,
                'sales' => 90,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000019',
                'sku' => 'AIR-001',
                'barcode' => '899100100019',
                'name' => 'Air Mineral Botol 600 ml (1 Dus)',
                'category' => 'Susu & Minuman',
                'price' => 45000,
                'original_price' => 48000,
                'cost' => 38000,
                'unit' => 'Dus',
                'badge' => 'Hemat Dus',
                'stock' => 20,
                'image' => 'https://images.unsplash.com/photo-1548839140-29a749e1bc4e?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.9,
                'sales' => 220,
            ],
            [
                'id' => '00000000-0000-0000-0001-000000000020',
                'sku' => 'KRP-001',
                'barcode' => '899100100020',
                'name' => 'Keripik Singkong Renyah Balado 200 gr',
                'category' => 'Snack & Biskuit',
                'price' => 15000,
                'original_price' => 17000,
                'cost' => 11800,
                'unit' => 'Bks',
                'badge' => 'Gurih Renyah',
                'stock' => 35,
                'image' => 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?auto=format&fit=crop&w=600&q=80',
                'rating' => 4.8,
                'sales' => 165,
            ],
        ];

        foreach ($productsData as $i => $p) {
            $prdId = sprintf('PRD-%03d', $i + 1);

            Product::updateOrCreate(['sku' => $p['sku']], [
                'id' => $p['id'],
                'prd_id' => $prdId,
                'barcode' => $p['barcode'],
                'name' => $p['name'],
                'category' => $p['category'],
                'selling_price' => $p['price'],
                'original_price' => $p['original_price'],
                'current_cost' => $p['cost'],
                'unit' => $p['unit'],
                'badge' => $p['badge'],
                'image' => $p['image'],
                'rating' => $p['rating'],
                'sales_count' => $p['sales'],
                'is_active' => true,
            ]);

            // Buat / Update Inventory Balance (Invariant: available = on_hand - reserved)
            InventoryBalance::updateOrCreate(['product_id' => $p['id']], [
                'on_hand' => $p['stock'],
                'reserved' => 0,
            ]);
        }

        // 7. Seed 1 Active Pickup Order (PKP-84920) untuk Ahmad Fauzi
        $activeOrderId = '00000000-0000-0000-0002-000000000001';
        $order = Order::updateOrCreate(['id' => $activeOrderId], [
            'member_id' => $memberId,
            'channel' => 'ONLINE_PICKUP',
            'status' => 'PENDING_PICKUP',
            'payment_choice' => 'CREDIT',
            'order_no' => 'ORD-20261117-001',
            'pickup_code' => 'PKP-84920',
            'total_amount' => 110500,
        ]);

        // Order Items (Beras Ramos 5kg & Minyak 2L)
        OrderItem::updateOrCreate(['order_id' => $order->id, 'product_id' => '00000000-0000-0000-0001-000000000001'], [
            'quantity' => 1,
            'unit_price_snapshot' => 74500,
            'hpp_snapshot' => 65000,
        ]);
        OrderItem::updateOrCreate(['order_id' => $order->id, 'product_id' => '00000000-0000-0000-0001-000000000002'], [
            'quantity' => 1,
            'unit_price_snapshot' => 36000,
            'hpp_snapshot' => 31000,
        ]);

        // Stock Reservation
        StockReservation::updateOrCreate(['order_id' => $order->id, 'product_id' => '00000000-0000-0000-0001-000000000001'], [
            'quantity' => 1,
        ]);
        StockReservation::updateOrCreate(['order_id' => $order->id, 'product_id' => '00000000-0000-0000-0001-000000000002'], [
            'quantity' => 1,
        ]);

        // 8. Seed 2 Tagihan Piutang Berjalan (Tempo 7 Hari)
        $pastOrderId1 = '00000000-0000-0000-0003-000000000001';
        Order::updateOrCreate(['id' => $pastOrderId1], [
            'member_id' => $memberId,
            'channel' => 'ONLINE_PICKUP',
            'status' => 'COMPLETED',
            'payment_choice' => 'CREDIT',
            'order_no' => 'ORD-20261112-089',
            'pickup_code' => 'PKP-11089',
            'total_amount' => 73500,
            'completed_at' => Carbon::now('Asia/Jakarta')->subDays(4),
        ]);

        Receivable::updateOrCreate(['order_id' => $pastOrderId1], [
            'member_id' => $memberId,
            'original_amount' => 73500,
            'outstanding_amount' => 73500,
            'due_date' => Carbon::now('Asia/Jakarta')->addDays(3)->toDateString(),
            'status' => 'OPEN',
        ]);

        $pastOrderId2 = '00000000-0000-0000-0003-000000000002';
        Order::updateOrCreate(['id' => $pastOrderId2], [
            'member_id' => $memberId,
            'channel' => 'ONLINE_PICKUP',
            'status' => 'COMPLETED',
            'payment_choice' => 'CREDIT',
            'order_no' => 'ORD-20261114-094',
            'pickup_code' => 'PKP-11094',
            'total_amount' => 74500,
            'completed_at' => Carbon::now('Asia/Jakarta')->subDays(2),
        ]);

        Receivable::updateOrCreate(['order_id' => $pastOrderId2], [
            'member_id' => $memberId,
            'original_amount' => 74500,
            'outstanding_amount' => 74500,
            'due_date' => Carbon::now('Asia/Jakarta')->addDays(5)->toDateString(),
            'status' => 'OPEN',
        ]);

        $this->command->info('StorefrontDatabaseSeeder berhasil dijalankan!');
    }
}
