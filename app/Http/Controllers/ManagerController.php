<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ManagerController (Portal Khusus Manajer Eksekutif & Analitik KoperasiKu)
 *
 * Mengelola analitik manajerial:
 * - Penjualan bulan ini (Omzet, HPP, OPEX, Laba kotor, Estimasi laba bersih, Kas masuk)
 * - Freshness data ETL PySpark Data Mart & evaluasi model machine learning (WAPE)
 * - Visualisasi grafik tren omzet vs laba (Chart.js)
 * - Monitoring portofolio & aging piutang 5 kelompok standar PRD KOPERADATA v1.4
 * - Rekomendasi restock dengan persetujuan manajerial manusia (human-in-the-loop)
 * - Form kebijakan limit piutang seragam global & aktivasi/blokir fasilitas anggota
 * - Master produk, HPP bergerak, kalkulasi margin, dan riwayat harga
 * - Antarmuka impor data Excel/CSV idempotent dengan validasi baris
 */
class ManagerController extends Controller
{
    /**
     * Dashboard Utama Eksekutif Manajer
     */
    public function index(): View
    {
        // KPI Finansial & Operasional Bulan Ini (November 2026)
        $metrics = [
            'omzet_monthly' => 48650000,
            'omzet_growth_percentage' => 12.4,
            'gross_profit' => 9243500,
            'gross_profit_margin' => 19.0,
            'operational_expense' => 3850000, // OPEX: listrik chiller, ATK kasir, pemeliharaan gerai
            'net_profit_estimate' => 5393500, // Gross Profit - OPEX
            'cash_inflow' => 36150000,
            'outstanding_receivables' => 12500000,
            'active_debtor_members' => 42,
            'completed_orders_count' => 548,
            'data_quality_status' => 'COMPLETE', // AC-18: 'COMPLETE' (Data HPP & Biaya Lengkap)
            'last_sync_at' => '16 Nov 2026, 15:00 WIB',
            'etl_status' => 'FRESH', // FRESH | STALE
        ];

        // Komposisi Metode Pembayaran Bulan Ini
        $paymentComposition = [
            ['label' => 'Tunai di Kasir (Cash)', 'percentage' => 55, 'amount' => 26757500, 'color' => '#15803d'],
            ['label' => 'Piutang Anggota (Tempo 7 Hari)', 'percentage' => 25, 'amount' => 12162500, 'color' => '#1d4ed8'],
            ['label' => 'Transfer Bank / QRIS', 'percentage' => 20, 'amount' => 9730000, 'color' => '#b45309'],
        ];

        // Data Tren Penjualan 7 Hari Terakhir untuk Chart.js
        $salesTrend = [
            'labels' => ['10 Nov', '11 Nov', '12 Nov', '13 Nov', '14 Nov', '15 Nov', '16 Nov'],
            'omzet' => [6200000, 6800000, 7100000, 6500000, 8200000, 9400000, 4450000],
            'profit' => [1180000, 1290000, 1350000, 1235000, 1558000, 1786000, 845500],
            'opex' => [550000, 550000, 550000, 550000, 550000, 550000, 550000],
        ];

        // Top 5 Produk Terlaris & Prediksi Restock (Forecasting Data Mart PIC D/E)
        $topSellingProducts = [
            [
                'rank' => 1,
                'sku' => 'SBK-BRS-001',
                'name' => 'Beras Premium Ramos 5 Kg',
                'category' => 'Sembako & Beras',
                'sold_qty' => 340,
                'unit' => 'Sak',
                'omzet' => 25330000,
                'stock_available' => 45,
                'stock_reserved' => 12,
                'lead_time_days' => 2,
                'safety_stock' => 30,
                'forecast_demand' => 180,
                'stock_days_left' => 4,
                'model_eval' => 'Baseline vs ML • WAPE 7.2%',
                'model_status' => 'OPTIMAL',
                'restock_qty' => 200,
                'unit_price' => 74500,
                'supplier' => 'PT Pangan Mandiri Jawa Barat',
            ],
            [
                'rank' => 2,
                'sku' => 'MNY-GRG-002',
                'name' => 'Minyak Goreng Pouch 2 Liter',
                'category' => 'Minyak & Bumbu',
                'sold_qty' => 280,
                'unit' => 'Pouch',
                'omzet' => 10080000,
                'stock_available' => 38,
                'stock_reserved' => 8,
                'lead_time_days' => 3,
                'safety_stock' => 25,
                'forecast_demand' => 140,
                'stock_days_left' => 6,
                'model_eval' => 'Baseline vs ML • WAPE 8.9%',
                'model_status' => 'OPTIMAL',
                'restock_qty' => 150,
                'unit_price' => 36000,
                'supplier' => 'Distributor Sawit Mas Dago',
            ],
            [
                'rank' => 3,
                'sku' => 'SBK-GLA-003',
                'name' => 'Gula Pasir Kristal Putih 1 Kg',
                'category' => 'Sembako & Beras',
                'sold_qty' => 210,
                'unit' => 'Bungkus',
                'omzet' => 3675000,
                'stock_available' => 95,
                'stock_reserved' => 5,
                'lead_time_days' => 2,
                'safety_stock' => 20,
                'forecast_demand' => 80,
                'stock_days_left' => 12,
                'model_eval' => 'Baseline vs ML • WAPE 6.5%',
                'model_status' => 'SAFE',
                'restock_qty' => 0,
                'unit_price' => 17500,
                'supplier' => 'PT PG Rajawali II',
            ],
            [
                'rank' => 4,
                'sku' => 'SGR-TLR-004',
                'name' => 'Telur Ayam Negeri Fresh 1 Kg',
                'category' => 'Produk Segar',
                'sold_qty' => 175,
                'unit' => 'Kg',
                'omzet' => 4987500,
                'stock_available' => 20,
                'stock_reserved' => 6,
                'lead_time_days' => 1,
                'safety_stock' => 15,
                'forecast_demand' => 65,
                'stock_days_left' => 3,
                'model_eval' => 'Baseline vs ML • WAPE 9.8%',
                'model_status' => 'OPTIMAL',
                'restock_qty' => 75,
                'unit_price' => 28500,
                'supplier' => 'Peternakan Unggas Sejahtera Lembang',
            ],
            [
                'rank' => 5,
                'sku' => 'MNM-UHT-005',
                'name' => 'Susu UHT Full Cream 1 Liter',
                'category' => 'Susu & Minuman',
                'sold_qty' => 140,
                'unit' => 'Kotak',
                'omzet' => 2730000,
                'stock_available' => 42,
                'stock_reserved' => 4,
                'lead_time_days' => 3,
                'safety_stock' => 18,
                'forecast_demand' => 70,
                'stock_days_left' => 8,
                'model_eval' => 'Insufficient Data (SKU Baru)',
                'model_status' => 'INSUFFICIENT',
                'restock_qty' => 60,
                'unit_price' => 19500,
                'supplier' => 'PT Ultra Dairy Bandung',
            ],
        ];

        // Portofolio & Aging Piutang 5 Kelompok Standar PRD (FR-08B & 12.3)
        $receivablesAging = [
            'current' => [
                'label' => 'Current (Belum Jatuh Tempo)',
                'range' => '0 – 7 Hari Kalender',
                'amount' => 8850000,
                'count' => 30,
                'percentage' => 70.8,
                'badge' => 'bg-success',
                'text_color' => 'text-success',
            ],
            'overdue_1_30' => [
                'label' => '1 – 30 Hari Lewat Tempo',
                'range' => 'Hari ke 8 – 37',
                'amount' => 2450000,
                'count' => 8,
                'percentage' => 19.6,
                'badge' => 'bg-warning text-dark',
                'text_color' => 'text-warning-emphasis',
            ],
            'overdue_31_60' => [
                'label' => '31 – 60 Hari Lewat Tempo',
                'range' => 'Hari ke 38 – 67',
                'amount' => 825000,
                'count' => 3,
                'percentage' => 6.6,
                'badge' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'text_color' => 'text-danger',
            ],
            'overdue_61_90' => [
                'label' => '61 – 90 Hari Lewat Tempo',
                'range' => 'Hari ke 68 – 97',
                'amount' => 250000,
                'count' => 1,
                'percentage' => 2.0,
                'badge' => 'bg-danger text-white',
                'text_color' => 'text-danger',
            ],
            'overdue_gt_90' => [
                'label' => '>90 Hari Lewat Tempo (Macet)',
                'range' => 'Hari ke 98+',
                'amount' => 125000,
                'count' => 1,
                'percentage' => 1.0,
                'badge' => 'bg-dark text-white',
                'text_color' => 'text-dark',
            ],
        ];

        return view('manager.dashboard', compact(
            'metrics',
            'paymentComposition',
            'salesTrend',
            'topSellingProducts',
            'receivablesAging'
        ));
    }

    /**
     * Pengaturan Kebijakan Limit Piutang Global Seragam & Status Fasilitas Anggota (FR-08A)
     */
    public function kredit(): View
    {
        $globalLimit = 1000000; // Limit tunggal seragam seluruh anggota
        $dueDateDays = 7; // Parameter jatuh tempo MVP terkunci 7 hari kalender
        $totalMembers = 245;
        $activeMembers = 238;
        $blockedMembers = 7;

        // Daftar Fasilitas Anggota (Active / Blocked) - Tidak ada kolom limit individu
        $memberCreditList = [
            [
                'id' => 'EMP-001',
                'name' => 'Ahmad Fauzi',
                'department' => 'Teknik Mesin Manufaktur',
                'status' => 'ACTIVE',
                'current_debt' => 430000,
                'overdue_count' => 0,
                'oldest_invoice_age' => '4 Hari (Current)',
                'is_overdue' => false,
            ],
            [
                'id' => 'EMP-015',
                'name' => 'Siti Rahmawati',
                'department' => 'Keuangan & Administrasi',
                'status' => 'ACTIVE',
                'current_debt' => 0,
                'overdue_count' => 0,
                'oldest_invoice_age' => 'N/A',
                'is_overdue' => false,
            ],
            [
                'id' => 'EMP-028',
                'name' => 'Dewi Lestari',
                'department' => 'Teknik Perancangan & Gambar Mesin',
                'status' => 'ACTIVE',
                'current_debt' => 520000,
                'overdue_count' => 0,
                'oldest_invoice_age' => '5 Hari (Current)',
                'is_overdue' => false,
            ],
            [
                'id' => 'EMP-035',
                'name' => 'Hendra Wijaya',
                'department' => 'Teknik Pengecoran Logam',
                'status' => 'BLOCKED',
                'current_debt' => 375000,
                'overdue_count' => 1,
                'oldest_invoice_age' => '11 Hari (Overdue 4 Hari)',
                'is_overdue' => true,
            ],
            [
                'id' => 'EMP-042',
                'name' => 'Bambang Soedjarwo',
                'department' => 'Teknik Otomasi Manufaktur',
                'status' => 'ACTIVE',
                'current_debt' => 180000,
                'overdue_count' => 0,
                'oldest_invoice_age' => '2 Hari (Current)',
                'is_overdue' => false,
            ],
            [
                'id' => 'EMP-059',
                'name' => 'Rina Kusuma',
                'department' => 'Laboratorium Metrologi Industri',
                'status' => 'BLOCKED',
                'current_debt' => 620000,
                'overdue_count' => 1,
                'oldest_invoice_age' => '19 Hari (Overdue 12 Hari)',
                'is_overdue' => true,
            ],
            [
                'id' => 'EMP-088',
                'name' => 'Agus Santoso',
                'department' => 'Pemeliharaan Fasilitas Kampus',
                'status' => 'ACTIVE',
                'current_debt' => 890000,
                'overdue_count' => 0,
                'oldest_invoice_age' => '6 Hari (Current)',
                'is_overdue' => false,
            ],
            [
                'id' => 'EMP-112',
                'name' => 'Nurul Hidayati',
                'department' => 'Perpustakaan & Dokumentasi',
                'status' => 'BLOCKED',
                'current_debt' => 125000,
                'overdue_count' => 1,
                'oldest_invoice_age' => '104 Hari (>90 Hari Macet)',
                'is_overdue' => true,
            ],
        ];

        // Audit Log Perubahan Kebijakan Limit Global
        $policyAuditLogs = [
            [
                'timestamp' => '10 Nov 2026 09:15 WIB',
                'actor' => 'Ir. Sudirman (Manajer Koperasi)',
                'previous_limit' => 750000,
                'new_limit' => 1000000,
                'note' => 'Penyesuaian limit seragam menghadapi lonjakan kebutuhan sembako akhir tahun (Rapat Pleno Pengurus).',
            ],
            [
                'timestamp' => '01 Sep 2026 14:00 WIB',
                'actor' => 'Ir. Sudirman (Manajer Koperasi)',
                'previous_limit' => 500000,
                'new_limit' => 750000,
                'note' => 'Evaluasi pilot project fasilitas tempo 7 hari bunga 0%, kolektibilitas anggota 98.2%.',
            ],
            [
                'timestamp' => '01 Jul 2026 08:30 WIB',
                'actor' => 'Budi Hartono (Ketua Koperasi)',
                'previous_limit' => 0,
                'new_limit' => 500000,
                'note' => 'Penetapan awal batas pagu kredit sembako konsumsi anggota POLMAN Bandung.',
            ],
        ];

        return view('manager.kredit', compact(
            'globalLimit',
            'dueDateDays',
            'totalMembers',
            'activeMembers',
            'blockedMembers',
            'memberCreditList',
            'policyAuditLogs'
        ));
    }

    /**
     * Monitoring Risiko & Detail Portofolio Piutang Anggota (FR-08B & Bagian 12.3)
     */
    public function piutang(): View
    {
        // 5 Aging summary metrics
        $agingSummary = [
            'total_receivables' => 12500000,
            'current' => 8850000,
            'overdue_1_30' => 2450000,
            'overdue_31_60' => 825000,
            'overdue_61_90' => 250000,
            'overdue_gt_90' => 125000,
            'total_debtors' => 42,
            'overdue_debtors' => 13,
        ];

        // Detail Seluruh Faktur Piutang
        $receivablesList = [
            [
                'invoice_no' => 'INV-2026-1108',
                'member_id' => 'EMP-001',
                'member_name' => 'Ahmad Fauzi',
                'department' => 'Teknik Mesin',
                'pickup_date' => '08 Nov 2026',
                'due_date' => '15 Nov 2026',
                'days_past_due' => 1,
                'aging_bucket' => '1_30',
                'aging_label' => '1–30 Hari Lewat Tempo',
                'total_amount' => 230000,
                'balance_due' => 230000,
                'status' => 'OVERDUE',
            ],
            [
                'invoice_no' => 'INV-2026-1111',
                'member_id' => 'EMP-001',
                'member_name' => 'Ahmad Fauzi',
                'department' => 'Teknik Mesin',
                'pickup_date' => '11 Nov 2026',
                'due_date' => '18 Nov 2026',
                'days_past_due' => 0,
                'aging_bucket' => 'current',
                'aging_label' => 'Current (Lancar)',
                'total_amount' => 200000,
                'balance_due' => 200000,
                'status' => 'OPEN',
            ],
            [
                'invoice_no' => 'INV-2026-1112',
                'member_id' => 'EMP-028',
                'member_name' => 'Dewi Lestari',
                'department' => 'Perancangan Gambar',
                'pickup_date' => '12 Nov 2026',
                'due_date' => '19 Nov 2026',
                'days_past_due' => 0,
                'aging_bucket' => 'current',
                'aging_label' => 'Current (Lancar)',
                'total_amount' => 520000,
                'balance_due' => 520000,
                'status' => 'OPEN',
            ],
            [
                'invoice_no' => 'INV-2026-1105',
                'member_id' => 'EMP-035',
                'member_name' => 'Hendra Wijaya',
                'department' => 'Pengecoran Logam',
                'pickup_date' => '05 Nov 2026',
                'due_date' => '12 Nov 2026',
                'days_past_due' => 4,
                'aging_bucket' => '1_30',
                'aging_label' => '1–30 Hari Lewat Tempo',
                'total_amount' => 375000,
                'balance_due' => 375000,
                'status' => 'OVERDUE',
            ],
            [
                'invoice_no' => 'INV-2026-1114',
                'member_id' => 'EMP-042',
                'member_name' => 'Bambang Soedjarwo',
                'department' => 'Otomasi Manufaktur',
                'pickup_date' => '14 Nov 2026',
                'due_date' => '21 Nov 2026',
                'days_past_due' => 0,
                'aging_bucket' => 'current',
                'aging_label' => 'Current (Lancar)',
                'total_amount' => 380000,
                'balance_due' => 180000,
                'status' => 'PARTIALLY_PAID',
            ],
            [
                'invoice_no' => 'INV-2026-1028',
                'member_id' => 'EMP-059',
                'member_name' => 'Rina Kusuma',
                'department' => 'Metrologi Industri',
                'pickup_date' => '28 Okt 2026',
                'due_date' => '04 Nov 2026',
                'days_past_due' => 12,
                'aging_bucket' => '1_30',
                'aging_label' => '1–30 Hari Lewat Tempo',
                'total_amount' => 620000,
                'balance_due' => 620000,
                'status' => 'OVERDUE',
            ],
            [
                'invoice_no' => 'INV-2026-0925',
                'member_id' => 'EMP-067',
                'member_name' => 'Dedy Kurniawan',
                'department' => 'Teknik Elektro',
                'pickup_date' => '25 Sep 2026',
                'due_date' => '02 Okt 2026',
                'days_past_due' => 45,
                'aging_bucket' => '31_60',
                'aging_label' => '31–60 Hari Lewat Tempo',
                'total_amount' => 825000,
                'balance_due' => 825000,
                'status' => 'OVERDUE',
            ],
            [
                'invoice_no' => 'INV-2026-0818',
                'member_id' => 'EMP-079',
                'member_name' => 'Yusuf Maulana',
                'department' => 'Bengkel Perkakas',
                'pickup_date' => '18 Agu 2026',
                'due_date' => '25 Agu 2026',
                'days_past_due' => 83,
                'aging_bucket' => '61_90',
                'aging_label' => '61–90 Hari Lewat Tempo',
                'total_amount' => 250000,
                'balance_due' => 250000,
                'status' => 'OVERDUE',
            ],
            [
                'invoice_no' => 'INV-2026-0720',
                'member_id' => 'EMP-112',
                'member_name' => 'Nurul Hidayati',
                'department' => 'Perpustakaan',
                'pickup_date' => '20 Jul 2026',
                'due_date' => '27 Jul 2026',
                'days_past_due' => 112,
                'aging_bucket' => 'gt_90',
                'aging_label' => '>90 Hari Lewat (Macet)',
                'total_amount' => 125000,
                'balance_due' => 125000,
                'status' => 'OVERDUE',
            ],
            [
                'invoice_no' => 'INV-2026-1102',
                'member_id' => 'EMP-015',
                'member_name' => 'Siti Rahmawati',
                'department' => 'Keuangan',
                'pickup_date' => '02 Nov 2026',
                'due_date' => '09 Nov 2026',
                'days_past_due' => 0,
                'aging_bucket' => 'paid',
                'aging_label' => 'Lunas (Kas Masuk)',
                'total_amount' => 310000,
                'balance_due' => 0,
                'status' => 'PAID',
            ],
        ];

        return view('manager.piutang', compact('agingSummary', 'receivablesList'));
    }

    /**
     * Master Produk, Harga Jual & HPP Moving Weighted Average (FR-05)
     */
    public function produk(): View
    {
        $products = [
            [
                'sku' => 'SBK-BRS-001',
                'barcode' => '8991001100223',
                'name' => 'Beras Premium Ramos 5 Kg',
                'category' => 'Sembako & Beras',
                'unit' => 'Sak',
                'stock_physical' => 57,
                'stock_reserved' => 12,
                'stock_available' => 45,
                'hpp' => 61000, // Moving weighted average HPP
                'selling_price' => 74500,
                'margin_rp' => 13500,
                'margin_percent' => 18.1,
                'status' => 'ACTIVE',
                'last_price_update' => '01 Nov 2026',
            ],
            [
                'sku' => 'MNY-GRG-002',
                'barcode' => '8992002200334',
                'name' => 'Minyak Goreng Pouch 2 Liter',
                'category' => 'Minyak & Bumbu',
                'unit' => 'Pouch',
                'stock_physical' => 46,
                'stock_reserved' => 8,
                'stock_available' => 38,
                'hpp' => 29500,
                'selling_price' => 36000,
                'margin_rp' => 6500,
                'margin_percent' => 18.1,
                'status' => 'ACTIVE',
                'last_price_update' => '05 Nov 2026',
            ],
            [
                'sku' => 'SBK-GLA-003',
                'barcode' => '8993003300445',
                'name' => 'Gula Pasir Kristal Putih 1 Kg',
                'category' => 'Sembako & Beras',
                'unit' => 'Bungkus',
                'stock_physical' => 100,
                'stock_reserved' => 5,
                'stock_available' => 95,
                'hpp' => 14200,
                'selling_price' => 17500,
                'margin_rp' => 3300,
                'margin_percent' => 18.9,
                'status' => 'ACTIVE',
                'last_price_update' => '15 Okt 2026',
            ],
            [
                'sku' => 'SGR-TLR-004',
                'barcode' => '8994004400556',
                'name' => 'Telur Ayam Negeri Fresh 1 Kg',
                'category' => 'Produk Segar',
                'unit' => 'Kg',
                'stock_physical' => 26,
                'stock_reserved' => 6,
                'stock_available' => 20,
                'hpp' => 23500,
                'selling_price' => 28500,
                'margin_rp' => 5000,
                'margin_percent' => 17.5,
                'status' => 'ACTIVE',
                'last_price_update' => '10 Nov 2026',
            ],
            [
                'sku' => 'MNM-UHT-005',
                'barcode' => '8995005500667',
                'name' => 'Susu UHT Full Cream 1 Liter',
                'category' => 'Susu & Minuman',
                'unit' => 'Kotak',
                'stock_physical' => 46,
                'stock_reserved' => 4,
                'stock_available' => 42,
                'hpp' => 15800,
                'selling_price' => 19500,
                'margin_rp' => 3700,
                'margin_percent' => 19.0,
                'status' => 'ACTIVE',
                'last_price_update' => '12 Nov 2026',
            ],
            [
                'sku' => 'KBT-SBN-006',
                'barcode' => '8996006600778',
                'name' => 'Sabun Mandi Cair Anti Bakteri 450ml',
                'category' => 'Kebutuhan Rumah',
                'unit' => 'Pouch',
                'stock_physical' => 35,
                'stock_reserved' => 2,
                'stock_available' => 33,
                'hpp' => 18200,
                'selling_price' => 22500,
                'margin_rp' => 4300,
                'margin_percent' => 19.1,
                'status' => 'ACTIVE',
                'last_price_update' => '20 Okt 2026',
            ],
        ];

        $summary = [
            'total_sku' => 6,
            'total_inventory_value' => 8420000,
            'avg_margin_percent' => 18.5,
        ];

        return view('manager.produk', compact('products', 'summary'));
    }

    /**
     * Impor Data Historis Excel/CSV Idempotent (FR-03)
     */
    public function import(): View
    {
        // Mock dataset validation preview for historical import
        $importHistory = [
            [
                'id' => 'IMP-2026-004',
                'data_type' => 'Master Produk',
                'filename' => 'master_produk_nov_2026.xlsx',
                'uploaded_at' => '15 Nov 2026 10:20 WIB',
                'total_rows' => 45,
                'valid_rows' => 45,
                'error_rows' => 0,
                'status' => 'COMMITTED',
                'actor' => 'Ir. Sudirman',
            ],
            [
                'id' => 'IMP-2026-003',
                'data_type' => 'Data Anggota',
                'filename' => 'anggota_kopeg_q4.csv',
                'uploaded_at' => '01 Nov 2026 14:10 WIB',
                'total_rows' => 245,
                'valid_rows' => 245,
                'error_rows' => 0,
                'status' => 'COMMITTED',
                'actor' => 'Ir. Sudirman',
            ],
            [
                'id' => 'IMP-2026-002',
                'data_type' => 'Stok Awal Cut-Off',
                'filename' => 'stok_opname_okt.xlsx',
                'uploaded_at' => '31 Okt 2026 18:45 WIB',
                'total_rows' => 60,
                'valid_rows' => 58,
                'error_rows' => 2,
                'status' => 'COMMITTED',
                'actor' => 'Ir. Sudirman',
            ],
        ];

        return view('manager.import', compact('importHistory'));
    }
}
