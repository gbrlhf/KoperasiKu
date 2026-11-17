<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MemberController (Portal Khusus Pelanggan / Anggota KoperasiKu)
 *
 * Mengelola dashboard resmi anggota koperasi:
 * - Kartu identitas anggota digital & ringkasan simpanan
 * - Pemantauan limit piutang, status jatuh tempo, dan rincian faktur
 * - Tiket kode pickup interaktif bergaya Boarding Pass
 * - Etalase belanja sembako cepat dengan integrasi modal rincian marketplace
 * - Riwayat transaksi pengambilan barang
 */
class MemberController extends Controller
{
    /**
     * Data master anggota dan kondisi finansial/pesanan berjalan.
     */
    private function getMemberData(): array
    {
        // 1. Profil & Keanggotaan Anggota (ID Card & Simpanan)
        $member = [
            'id' => 'EMP-001',
            'name' => 'Ahmad Fauzi',
            'unit' => 'Teknik Mesin & Manufaktur POLMAN',
            'join_date' => '12 Maret 2021',
            'membership_tier' => 'Anggota Aktif',
            'status' => 'TERVERIFIKASI',
            'phone' => '0812-8821-9042',
            'email' => 'ahmad.fauzi@polman-bandung.ac.id',
            'simpanan_pokok' => 500000,
            'simpanan_wajib' => 950000,
            'total_simpanan' => 1450000,
            'shu_estimasi' => 84500,
            'poin_belanja' => 420,
            'monthly_spend' => 420000,
            'savings_earned' => 46500,
        ];

        // 2. Status Piutang & Limit Belanja Anggota (Tempo 7 Hari)
        $receivableStatus = [
            'has_debt' => true,
            'total_debt' => 148000,
            'due_date' => '19 November 2026',
            'days_remaining' => 3,
            'is_overdue' => false,
            'credit_limit' => 1000000,
            'available_limit' => 852000,
            'limit_used_percent' => 14.8,
            'active_invoices' => [
                [
                    'invoice_no' => 'INV-2026-1108',
                    'date' => '12 Nov 2026',
                    'items_summary' => 'Beras Ramos 5kg, Minyak Goreng 2L',
                    'amount' => 110500,
                    'due_date' => '19 Nov 2026',
                    'status' => 'UNPAID',
                    'days_left' => 3,
                ],
                [
                    'invoice_no' => 'INV-2026-1110',
                    'date' => '14 Nov 2026',
                    'items_summary' => 'Gula Pasir 1kg, Kopi Robusta 250gr',
                    'amount' => 37500,
                    'due_date' => '21 Nov 2026',
                    'status' => 'UNPAID',
                    'days_left' => 5,
                ],
            ],
        ];

        // 3. Pesanan Aktif Siap Ambil (Tiket Boarding Pass Pickup)
        $activeOrders = [
            [
                'pickup_code' => 'PKP-84920',
                'order_no' => 'ORD-2026-1116-01',
                'date' => '16 Nov 2026 &bull; 09:15 WIB',
                'status' => 'READY_FOR_PICKUP',
                'status_label' => 'Siap Diambil di Kasir',
                'status_step' => 3, // 1: Diterima, 2: Dikemas, 3: Siap Diambil di Kasir
                'payment_method' => 'CASH',
                'payment_label' => 'Bayar Tunai di Kasir',
                'total_amount' => 110500,
                'items_count' => 2,
                'store_location' => 'Gerai KOPEG Mart Jl. Kanayakan No. A11 Dago',
                'store_hours' => 'Senin – Sabtu: 07.00 – 18.00 WIB',
                'items' => [
                    ['name' => 'Beras Premium Ramos 5 Kg', 'qty' => 1, 'unit' => 'Sak', 'price' => 74500],
                    ['name' => 'Minyak Goreng Pouch 2 Liter', 'qty' => 1, 'unit' => 'Pouch', 'price' => 36000],
                ],
            ],
        ];

        // 4. Riwayat Pesanan Selesai / Nota Digital
        $orderHistory = [
            [
                'pickup_code' => 'PKP-77210',
                'order_no' => 'ORD-2026-1105-02',
                'date' => '05 Nov 2026 &bull; 14:20 WIB',
                'status' => 'COMPLETED',
                'status_label' => 'Selesai Diambil',
                'payment_method' => 'TEMPO_7_HARI',
                'payment_label' => 'Piutang Anggota',
                'total_amount' => 87000,
                'items_summary' => 'Deterjen Bubuk 800gr, Susu UHT 1L, Telur Ayam 1kg',
                'items_count' => 3,
                'picked_up_at' => '05 Nov 2026, 16:40 WIB',
            ],
            [
                'pickup_code' => 'PKP-65192',
                'order_no' => 'ORD-2026-1028-04',
                'date' => '28 Okt 2026 &bull; 11:05 WIB',
                'status' => 'COMPLETED',
                'status_label' => 'Selesai Diambil',
                'payment_method' => 'TRANSFER_QRIS',
                'payment_label' => 'QRIS Bank / E-Wallet',
                'total_amount' => 124500,
                'items_summary' => 'Beras Ramos 5kg, Kopi Robusta 250gr, Gula Pasir 1kg',
                'items_count' => 3,
                'picked_up_at' => '28 Okt 2026, 12:15 WIB',
            ],
        ];

        // 5. Kategori & Produk Sembako Anggota
        $categories = [
            'Semua',
            'Sembako & Beras',
            'Minyak & Bumbu',
            'Susu & Minuman',
            'Snack & Biskuit',
            'Sabun & Cuci',
            'Produk Segar',
        ];

        $products = [
            [
                'id' => 'PRD-001',
                'sku' => 'SEM-001',
                'name' => 'Beras Premium Ramos 5 Kg',
                'category' => 'Sembako & Beras',
                'price' => 74500,
                'original_price' => 82000,
                'available_stock' => 32,
                'unit' => 'Sak',
                'badge' => 'Terlaris',
                'rating' => 4.9,
                'reviews_count' => 48,
                'sold_count' => '340+ terjual',
                'icon' => 'bi-box-seam',
                'image' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=600&q=80',
                'description' => 'Beras Ramos Super kualitas premium pilihan petani binaan. Bulir putih bersih, pulen saat dimasak, tanpa pemutih, tanpa pengawet sintetis, dan tanpa pewangi kimia. Pilihan utama konsumsi keluarga besar pegawai POLMAN Bandung.',
                'specs' => [
                    'Berat Bersih' => '5 Kilogram',
                    'Kualitas' => 'Premium (Pulen Alami)',
                    'Sertifikasi' => 'Halal MUI & Kementan RI',
                    'Kemasan' => 'Karung Plastik Tebal Anti Lembap',
                    'Penyimpanan' => 'Simpan di tempat sejuk & tertutup rapat',
                ],
            ],
            [
                'id' => 'PRD-002',
                'sku' => 'MNY-001',
                'name' => 'Minyak Goreng Pouch 2 Liter',
                'category' => 'Minyak & Bumbu',
                'price' => 36000,
                'original_price' => 40000,
                'available_stock' => 18,
                'unit' => 'Pouch',
                'badge' => 'Promo Spesial',
                'rating' => 4.9,
                'reviews_count' => 36,
                'sold_count' => '280+ terjual',
                'icon' => 'bi-droplet-fill',
                'image' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=600&q=80',
                'description' => 'Minyak goreng kelapa sawit murni kualitas fortifikasi vitamin A. Hasil 2x penyaringan menghasilkan minyak yang jernih, stabil pada suhu pemanasan tinggi, tidak mudah menghitam, dan membuat gorengan renyah tanpa bau tengik.',
                'specs' => [
                    'Volume Bersih' => '2 Liter',
                    'Komposisi' => 'Kelapa Sawit Pilihan & Vitamin A',
                    'Sertifikasi' => 'Halal & BPOM RI',
                    'Kemasan' => 'Standing Pouch Anti Tumpah',
                    'Masa Simpan' => '18 Bulan',
                ],
            ],
            [
                'id' => 'PRD-003',
                'sku' => 'GUL-001',
                'name' => 'Gula Pasir Kristal Putih 1 Kg',
                'category' => 'Sembako & Beras',
                'price' => 17500,
                'original_price' => 18500,
                'available_stock' => 45,
                'unit' => 'Bungkus',
                'badge' => 'Harga Resmi',
                'rating' => 4.8,
                'reviews_count' => 22,
                'sold_count' => '195+ terjual',
                'icon' => 'bi-archive',
                'image' => 'https://images.unsplash.com/photo-1587132137056-bfbf0166836e?auto=format&fit=crop&w=600&q=80',
                'description' => 'Gula tebu murni dengan butiran kristal putih alami. Diproses secara higienis dari perkebunan tebu terbaik nasional, larut cepat tanpa residu berlebih, cocok untuk pemanis minuman hangat dan aneka olahan kue keluarga.',
                'specs' => [
                    'Berat Bersih' => '1.000 gram (1 Kg)',
                    'Bahan Baku' => '100% Tebu Alami',
                    'Sertifikasi' => 'SNI & Halal Indonesia',
                    'Kemasan' => 'Plastik Food Grade Kedap Udara',
                    'Kemurnian' => 'Ekstra Bersih Tanpa Pewarna',
                ],
            ],
            [
                'id' => 'PRD-004',
                'sku' => 'SSU-001',
                'name' => 'Susu UHT Full Cream 1 Liter',
                'category' => 'Susu & Minuman',
                'price' => 19500,
                'original_price' => 22000,
                'available_stock' => 24,
                'unit' => 'Kotak',
                'badge' => 'Favorit',
                'rating' => 4.9,
                'reviews_count' => 31,
                'sold_count' => '210+ terjual',
                'icon' => 'bi-cup-straw',
                'image' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?auto=format&fit=crop&w=600&q=80',
                'description' => 'Susu sapi segar steril pilihan peternakan Lembang Bandung. Sumber kaya kalsium, fosfor, dan vitamin D untuk menjaga kebugaran tubuh serta mendukung tumbuh kembang anak-anak anggota.',
                'specs' => [
                    'Isi Bersih' => '1.000 ml',
                    'Rasa' => 'Plain Full Cream Gurih Alami',
                    'Teknologi' => 'Sterilisasi UHT Suhu Tinggi Higienis',
                    'Penyimpanan' => 'Setelah dibuka, simpan di kulkas maksimal 4 hari',
                    'Sertifikasi' => 'Halal & BPOM MD',
                ],
            ],
            [
                'id' => 'PRD-005',
                'sku' => 'TLR-001',
                'name' => 'Telur Ayam Negeri Fresh 1 Kg',
                'category' => 'Produk Segar',
                'price' => 28500,
                'original_price' => 31000,
                'available_stock' => 25,
                'unit' => 'Kg',
                'badge' => 'Pasokan Pagi',
                'rating' => 4.8,
                'reviews_count' => 17,
                'sold_count' => '160+ terjual',
                'icon' => 'bi-egg',
                'image' => 'https://images.unsplash.com/photo-1516467508483-a7212febe31a?auto=format&fit=crop&w=600&q=80',
                'description' => 'Telur ayam ras segar pasokan langsung peternak rekanan koperasi setiap pagi. Cangkang bersih, kuning telur padat dan segar, kaya omega serta protein tinggi untuk sarapan keluarga bergizi seimbang.',
                'specs' => [
                    'Kuantitas' => 'Isi 15-16 Butir per 1 Kg',
                    'Asal' => 'Peternakan Lokal Priangan',
                    'Kondisi' => 'Segar Bukan Telur Beku/Limbah',
                    'Penyimpanan' => 'Suhu ruang atau rak lemari es',
                    'Grading' => 'Grade A Terseleksi Utuh',
                ],
            ],
            [
                'id' => 'PRD-006',
                'sku' => 'TPG-001',
                'name' => 'Tepung Terigu Serbaguna 1 Kg',
                'category' => 'Sembako & Beras',
                'price' => 12500,
                'original_price' => 14000,
                'available_stock' => 30,
                'unit' => 'Bungkus',
                'badge' => null,
                'rating' => 4.7,
                'reviews_count' => 14,
                'sold_count' => '110+ terjual',
                'icon' => 'bi-bag',
                'image' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=600&q=80',
                'description' => 'Tepung terigu protein sedang serbaguna. Cocok untuk aneka gorengan renyah, martabak manis, bolu kukus, roti lembut, hingga pengental sup kuah keluarga.',
                'specs' => [
                    'Berat Bersih' => '1.000 gram',
                    'Kadar Protein' => 'Sedang (Serbaguna)',
                    'Fortifikasi' => 'Zat Besi, Seng, Vitamin B1 & B2',
                    'Sertifikasi' => 'SNI Wajib & Halal Indonesia',
                    'Masa Simpan' => '12 Bulan',
                ],
            ],
            [
                'id' => 'PRD-011',
                'sku' => 'SBN-001',
                'name' => 'Deterjen Bubuk Konsentrat 800 gr',
                'category' => 'Sabun & Cuci',
                'price' => 21000,
                'original_price' => 24000,
                'available_stock' => 15,
                'unit' => 'Bungkus',
                'badge' => 'Bersih',
                'rating' => 4.9,
                'reviews_count' => 16,
                'sold_count' => '140+ terjual',
                'icon' => 'bi-stars',
                'image' => 'https://images.unsplash.com/photo-1585421514738-01798e348b17?auto=format&fit=crop&w=600&q=80',
                'description' => 'Deterjen pembersih pakaian dengan formula enzim bio-aktif. Ampuh mengangkat noda minyak dan lumpur membandel tanpa merusak serat kain dan lembut di kulit tangan.',
                'specs' => [
                    'Berat Bersih' => '800 gram',
                    'Aroma' => 'Fresh Floral Semerbak Tahan Lama',
                    'Aplikasi' => 'Mesin Cuci Bukaan Atas & Cuci Tangan',
                    'Fitur' => 'Anti Bakteri & Anti Bau Apek',
                    'Ramah Lingkungan' => 'Surfaktan Biodegradable',
                ],
            ],
            [
                'id' => 'PRD-016',
                'sku' => 'KPI-001',
                'name' => 'Kopi Bubuk Arabika Robusta 250 gr',
                'category' => 'Susu & Minuman',
                'price' => 32000,
                'original_price' => 36000,
                'available_stock' => 14,
                'unit' => 'Bungkus',
                'badge' => 'Khas Bandung',
                'rating' => 4.9,
                'reviews_count' => 28,
                'sold_count' => '230+ terjual',
                'icon' => 'bi-cup-hot-fill',
                'image' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=600&q=80',
                'description' => 'Racikan istimewa biji kopi pilihan petani dataran tinggi Jawa Barat. Profil sangrai medium-dark menghasilkan aroma karamel pekat, body mantap, dan aftertaste manis alami.',
                'specs' => [
                    'Berat Bersih' => '250 gram',
                    'Profil Sangrai' => 'Medium Dark Roast',
                    'Komposisi' => 'Blend Arabika Priangan 60% & Robusta 40%',
                    'Kemasan' => 'Gusset Foil dengan One-Way Degassing Valve',
                    'Penggilingan' => 'Fine Grind (Cocok Kopi Tubruk & Moka Pot)',
                ],
            ],
        ];

        // Master lookup map untuk modal rincian produk
        $allProductsMap = [];
        foreach ($products as $p) {
            $allProductsMap[$p['id']] = $p;
        }

        return compact(
            'member',
            'receivableStatus',
            'activeOrders',
            'orderHistory',
            'categories',
            'products',
            'allProductsMap'
        );
    }

    /**
     * Dashboard Utama Anggota Koperasi (Ringkasan Lengkap)
     */
    public function index(): View
    {
        $data = $this->getMemberData();
        $data['activeTab'] = 'dashboard';

        return view('member.dashboard', $data);
    }

    /**
     * Katalog Belanja Sembako Khusus Anggota
     */
    public function katalog(): View
    {
        $data = $this->getMemberData();
        $data['activeTab'] = 'katalog';

        return view('member.dashboard', $data);
    }

    /**
     * Halaman Informasi Limit & Rincian Piutang Tempo 7 Hari
     */
    public function kredit(): View
    {
        $data = $this->getMemberData();
        $data['activeTab'] = 'kredit';

        return view('member.dashboard', $data);
    }

    /**
     * Halaman Riwayat Pesanan & Kode Pickup
     */
    public function pesanan(): View
    {
        $data = $this->getMemberData();
        $data['activeTab'] = 'pesanan';

        return view('member.dashboard', $data);
    }

    /**
     * Halaman Keranjang Belanja
     */
    public function keranjang(): View
    {
        return $this->katalog();
    }
}
