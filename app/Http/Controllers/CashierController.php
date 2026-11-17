<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CashierController (Portal Khusus Kasir & Petugas Gerai KoperasiKu)
 *
 * Mengelola operasional gerai:
 * - Monitoring stok barang (Stok fisik, reservasi pickup, stok tersedia kasir)
 * - Transaksi langsung meja kasir (POS Kasir & Barcode Lookup)
 * - Antrian pickup pesanan online anggota
 * - Pelunasan piutang konsumsi
 */
class CashierController extends Controller
{
    public function index(): View
    {
        // TODO: integrasi langsung dengan GET /api/v1/products, GET /api/v1/cashier/orders, POST /api/v1/pos/sales

        // Ringkasan Stok Gerai
        $stockSummary = [
            'total_sku' => 48,
            'safe_stock_count' => 42,
            'low_stock_count' => 6,
            'out_of_stock_count' => 0,
            'total_reserved_items' => 38,
        ];

        // Master Stok Barang Real-Time
        $stockItems = [
            [
                'sku' => 'SEM-001',
                'barcode' => '89999991001',
                'name' => 'Beras Premium Ramos 5 Kg',
                'category' => 'Sembako & Beras',
                'cost_price' => 68000,
                'sell_price' => 74500,
                'on_hand' => 45,
                'reserved' => 13,
                'available' => 32,
                'unit' => 'Sak',
                'status' => 'SAFE',
            ],
            [
                'sku' => 'MNY-001',
                'barcode' => '89999991002',
                'name' => 'Minyak Goreng Pouch 2 Liter',
                'category' => 'Minyak & Bumbu',
                'cost_price' => 32500,
                'sell_price' => 36000,
                'on_hand' => 26,
                'reserved' => 8,
                'available' => 18,
                'unit' => 'Pouch',
                'status' => 'SAFE',
            ],
            [
                'sku' => 'GUL-001',
                'barcode' => '89999991003',
                'name' => 'Gula Pasir Kristal Putih 1 Kg',
                'category' => 'Sembako & Beras',
                'cost_price' => 15200,
                'sell_price' => 17500,
                'on_hand' => 52,
                'reserved' => 7,
                'available' => 45,
                'unit' => 'Bungkus',
                'status' => 'SAFE',
            ],
            [
                'sku' => 'TLR-001',
                'barcode' => '89999991005',
                'name' => 'Telur Ayam Negeri Fresh 1 Kg',
                'category' => 'Produk Segar',
                'cost_price' => 25000,
                'sell_price' => 28500,
                'on_hand' => 30,
                'reserved' => 5,
                'available' => 25,
                'unit' => 'Kg',
                'status' => 'SAFE',
            ],
            [
                'sku' => 'KPI-001',
                'barcode' => '89999991008',
                'name' => 'Kopi Bubuk Arabika Robusta 250 gr',
                'category' => 'Susu & Minuman',
                'cost_price' => 27000,
                'sell_price' => 32000,
                'on_hand' => 18,
                'reserved' => 4,
                'available' => 14,
                'unit' => 'Bungkus',
                'status' => 'LOW',
            ],
            [
                'sku' => 'SBN-001',
                'barcode' => '89999991011',
                'name' => 'Deterjen Bubuk Konsentrat 800 gr',
                'category' => 'Sabun & Cuci',
                'cost_price' => 18000,
                'sell_price' => 21000,
                'on_hand' => 18,
                'reserved' => 3,
                'available' => 15,
                'unit' => 'Bungkus',
                'status' => 'SAFE',
            ],
        ];

        // Daftar Antrian Pickup yang Siap Diambil di Kasir
        $pickupQueue = [
            [
                'id' => 'ORD-1082',
                'pickup_code' => 'PKP-84920',
                'member_id' => 'EMP-001',
                'member_name' => 'Ahmad Fauzi',
                'created_at' => '16 Nov 2026 09:15',
                'items_summary' => 'Beras Ramos 5kg (1), Minyak Goreng 2L (1)',
                'payment_method' => 'CASH',
                'total_amount' => 110500,
                'payment_status' => 'PENDING_CASH',
                'status' => 'PENDING_PICKUP',
            ],
            [
                'id' => 'ORD-1083',
                'pickup_code' => 'PKP-73194',
                'member_id' => 'EMP-014',
                'member_name' => 'Budi Santoso',
                'created_at' => '16 Nov 2026 10:04',
                'items_summary' => 'Gula Pasir 1kg (2), Telur Ayam 1kg (1)',
                'payment_method' => 'CREDIT',
                'total_amount' => 63500,
                'payment_status' => 'CREDIT_APPROVED',
                'status' => 'PENDING_PICKUP',
            ],
            [
                'id' => 'ORD-1084',
                'pickup_code' => 'PKP-51922',
                'member_id' => 'EMP-028',
                'member_name' => 'Dewi Lestari',
                'created_at' => '16 Nov 2026 11:20',
                'items_summary' => 'Susu UHT 1L (2), Kopi Robusta (1)',
                'payment_method' => 'BANK_TRANSFER',
                'total_amount' => 71000,
                'payment_status' => 'PENDING_VERIFICATION',
                'transfer_ref' => 'TRX-BCA-51922',
                'status' => 'PENDING_PICKUP',
            ],
            [
                'id' => 'ORD-1085',
                'pickup_code' => 'PKP-91823',
                'member_id' => 'EMP-035',
                'member_name' => 'Dr. Hendra Wijaya',
                'created_at' => '16 Nov 2026 12:45',
                'items_summary' => 'Beras Ramos 5kg (1), Gula Pasir 1kg (1)',
                'payment_method' => 'BANK_TRANSFER',
                'total_amount' => 92000,
                'payment_status' => 'VERIFIED',
                'transfer_ref' => 'TRX-MANDIRI-91823',
                'status' => 'PENDING_PICKUP',
            ],
        ];

        return view('cashier.pos', compact(
            'stockSummary',
            'stockItems',
            'pickupQueue'
        ));
    }

    public function antrian(): View
    {
        return $this->index();
    }

    public function stok(): View
    {
        return $this->index();
    }

    public function piutang(): View
    {
        $globalCreditLimit = 1000000;

        $debtorMembers = [
            [
                'id' => 'EMP-001',
                'name' => 'Ahmad Fauzi',
                'unit' => 'Teknik Mesin & Manufaktur POLMAN',
                'phone' => '0812-8821-9042',
                'status' => 'TERVERIFIKASI',
                'has_overdue' => false,
                'global_limit' => 1000000,
                'total_debt' => 148000,
                'available_limit' => 852000,
                'earliest_due_date' => '19 Nov 2026',
                'days_remaining' => 3,
                'invoices' => [
                    [
                        'id' => 'INV-2026-1108',
                        'order_no' => 'ORD-2026-1108-01',
                        'pickup_date' => '12 Nov 2026',
                        'due_date' => '19 Nov 2026',
                        'days_left' => 3,
                        'items_summary' => 'Beras Premium Ramos 5 Kg (1)',
                        'original_amount' => 74500,
                        'paid_amount' => 0,
                        'remaining_amount' => 74500,
                        'status' => 'OPEN',
                    ],
                    [
                        'id' => 'INV-2026-1111',
                        'order_no' => 'ORD-2026-1111-03',
                        'pickup_date' => '14 Nov 2026',
                        'due_date' => '21 Nov 2026',
                        'days_left' => 5,
                        'items_summary' => 'Minyak Goreng 2L (1), Telur 1kg (1)',
                        'original_amount' => 73500,
                        'paid_amount' => 0,
                        'remaining_amount' => 73500,
                        'status' => 'OPEN',
                    ],
                ],
            ],
            [
                'id' => 'EMP-028',
                'name' => 'Dewi Lestari',
                'unit' => 'Administrasi Keuangan POLMAN',
                'phone' => '0813-7729-1102',
                'status' => 'TERVERIFIKASI',
                'has_overdue' => false,
                'global_limit' => 1000000,
                'total_debt' => 420000,
                'available_limit' => 580000,
                'earliest_due_date' => '17 Nov 2026',
                'days_remaining' => 1,
                'invoices' => [
                    [
                        'id' => 'INV-2026-1105',
                        'order_no' => 'ORD-2026-1105-02',
                        'pickup_date' => '10 Nov 2026',
                        'due_date' => '17 Nov 2026',
                        'days_left' => 1,
                        'items_summary' => 'Beras Ramos 5kg (2), Minyak 2L (2)',
                        'original_amount' => 221000,
                        'paid_amount' => 71000,
                        'remaining_amount' => 150000,
                        'status' => 'PARTIALLY_PAID',
                    ],
                    [
                        'id' => 'INV-2026-1109',
                        'order_no' => 'ORD-2026-1109-01',
                        'pickup_date' => '13 Nov 2026',
                        'due_date' => '20 Nov 2026',
                        'days_left' => 4,
                        'items_summary' => 'Susu UHT 1L (4), Gula Pasir 2kg (1)',
                        'original_amount' => 170000,
                        'paid_amount' => 0,
                        'remaining_amount' => 170000,
                        'status' => 'OPEN',
                    ],
                    [
                        'id' => 'INV-2026-1113',
                        'order_no' => 'ORD-2026-1113-04',
                        'pickup_date' => '15 Nov 2026',
                        'due_date' => '22 Nov 2026',
                        'days_left' => 6,
                        'items_summary' => 'Deterjen Bubuk 800gr (2), Sabun Cuci (2)',
                        'original_amount' => 100000,
                        'paid_amount' => 0,
                        'remaining_amount' => 100000,
                        'status' => 'OPEN',
                    ],
                ],
            ],
            [
                'id' => 'EMP-035',
                'name' => 'Dr. Hendra Wijaya',
                'unit' => 'Dosen Rekayasa Perancangan POLMAN',
                'phone' => '0811-2299-8811',
                'status' => 'TERVERIFIKASI',
                'has_overdue' => true,
                'global_limit' => 1000000,
                'total_debt' => 375000,
                'available_limit' => 625000,
                'earliest_due_date' => '09 Nov 2026',
                'days_remaining' => -7,
                'invoices' => [
                    [
                        'id' => 'INV-2026-1102',
                        'order_no' => 'ORD-2026-1102-01',
                        'pickup_date' => '02 Nov 2026',
                        'due_date' => '09 Nov 2026',
                        'days_left' => -7,
                        'items_summary' => 'Beras Ramos 5kg (2), Minyak 2L (2), Telur 1kg (2)',
                        'original_amount' => 275000,
                        'paid_amount' => 0,
                        'remaining_amount' => 275000,
                        'status' => 'OVERDUE',
                    ],
                    [
                        'id' => 'INV-2026-1110',
                        'order_no' => 'ORD-2026-1110-02',
                        'pickup_date' => '14 Nov 2026',
                        'due_date' => '21 Nov 2026',
                        'days_left' => 5,
                        'items_summary' => 'Kopi Robusta 250gr (2), Susu Kental Manis (3)',
                        'original_amount' => 100000,
                        'paid_amount' => 0,
                        'remaining_amount' => 100000,
                        'status' => 'OPEN',
                    ],
                ],
            ],
            [
                'id' => 'EMP-014',
                'name' => 'Siti Rahma',
                'unit' => 'Logistik & Pengadaan POLMAN',
                'phone' => '0815-6677-2211',
                'status' => 'TERVERIFIKASI',
                'has_overdue' => false,
                'global_limit' => 1000000,
                'total_debt' => 0,
                'available_limit' => 1000000,
                'earliest_due_date' => '-',
                'days_remaining' => 0,
                'invoices' => [],
            ],
        ];

        return view('cashier.piutang', compact('debtorMembers', 'globalCreditLimit'));
    }
}
