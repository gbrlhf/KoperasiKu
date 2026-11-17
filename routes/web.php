<?php

use App\Http\Controllers\CashierController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ReceivableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - KoperasiKu (PIC A - Frontend & UI/UX)
|--------------------------------------------------------------------------
| Menghubungkan 3 Portal Terpisah:
| 1. Landing Page Publik
| 2. Halaman Konsumen / Anggota (Pemesanan & Cek Hutang Saya)
| 3. Halaman Kasir (Stok Barang, POS Kasir & Verifikasi Pickup)
| 4. Halaman Manajer (Analitik Eksekutif & Penjualan Bulan Ini)
*/

// Halaman Beranda Publik (Etalase Storefront)
Route::get('/', [LandingController::class, 'index'])->name('landing');

// --------------------------------------------------------------------------
// 1. Umum & Autentikasi
// --------------------------------------------------------------------------
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// // TODO: integrasi langsung dengan auth API backend saat endpoint /api/v1/auth/login siap
Route::post('/login', function (Request $request) {
    $role = $request->input('role', 'MEMBER');

    session([
        'user' => [
            'id' => 'EMP-001',
            'name' => 'Ahmad Fauzi',
            'username' => $request->input('username', 'EMP-001'),
        ],
        'active_role' => $role,
        'available_roles' => ['MEMBER', 'CASHIER', 'MANAGER'],
    ]);

    return match ($role) {
        'CASHIER' => redirect()->route('cashier.pos')->with('success', 'Masuk ke Portal Kasir & Stok.'),
        'MANAGER' => redirect()->route('manager.dashboard')->with('success', 'Masuk ke Portal Analitik Manajer.'),
        default => redirect()->route('landing')->with('success', 'Masuk sebagai Anggota Koperasi (Ahmad Fauzi). Cek tagihan di menu Profil.'),
    };
})->name('login.submit');

// Switch Role Context (Memudahkan review UI 3 Halaman Berbeda)
Route::post('/mock-switch-role', function (Request $request) {
    $role = $request->input('role', 'MEMBER');
    $validRoles = ['MEMBER', 'CASHIER', 'MANAGER'];

    if (in_array($role, $validRoles)) {
        session(['active_role' => $role]);

        return match ($role) {
            'CASHIER' => redirect()->route('cashier.pos')->with('info', 'Beralih ke Portal Kasir & Stok.'),
            'MANAGER' => redirect()->route('manager.dashboard')->with('info', 'Beralih ke Portal Analitik Manajer.'),
            default => redirect()->route('landing')->with('info', 'Beralih ke Etalase Anggota (Landing Page).'),
        };
    }

    return back()->with('error', 'Peran tidak valid.');
})->name('mock.switch-role');

// --------------------------------------------------------------------------
// 2. Portal Pelanggan / Anggota (Disatukan di Landing Page + Drawer Profil)
// --------------------------------------------------------------------------
Route::prefix('member')->name('member.')->group(function () {
    Route::get('/', fn() => redirect()->route('landing'))->name('index');
    Route::get('/dashboard', fn() => redirect()->route('landing'))->name('dashboard');
    Route::get('/katalog', fn() => redirect()->route('landing'))->name('katalog');
    Route::get('/kredit', fn() => redirect()->route('landing', ['open' => 'profile']))->name('kredit');
    Route::get('/pesanan', fn() => redirect()->route('landing', ['open' => 'profile']))->name('pesanan');
    Route::get('/keranjang', fn() => redirect()->route('landing', ['open' => 'cart']))->name('keranjang');
});

// --------------------------------------------------------------------------
// 3. Portal Kasir / Petugas Toko (Stok Barang, POS & Verifikasi Pickup)
// --------------------------------------------------------------------------
Route::prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/', [CashierController::class, 'index'])->name('index');
    Route::get('/pos', [CashierController::class, 'index'])->name('pos');
    Route::get('/stok', [CashierController::class, 'stok'])->name('stok');
    Route::get('/antrian', [CashierController::class, 'antrian'])->name('antrian');
    Route::get('/piutang', [CashierController::class, 'piutang'])->name('piutang');
});

// --------------------------------------------------------------------------
// 4. Portal Manajer Eksekutif (Analitik Penjualan Bulan Ini, Laba & Piutang)
// --------------------------------------------------------------------------
Route::prefix('manager')->name('manager.')->group(function () {
    Route::get('/', [ManagerController::class, 'index'])->name('index');
    Route::get('/dashboard', [ManagerController::class, 'index'])->name('dashboard');
    Route::get('/kredit', [ManagerController::class, 'kredit'])->name('kredit');
    Route::get('/piutang', [ManagerController::class, 'piutang'])->name('piutang');
    Route::get('/produk', [ManagerController::class, 'produk'])->name('produk');
    Route::get('/import', [ManagerController::class, 'import'])->name('import');
});

// --------------------------------------------------------------------------
// 5. Integrasi Backend Transaksi Langsung (Checkout Storefront, POS & Pickup)
// --------------------------------------------------------------------------
Route::post('/checkout', [OrderController::class, 'store'])->name('checkout.submit');
Route::post('/cashier/confirm-pickup', [OrderController::class, 'confirmPickup'])->name('cashier.confirm-pickup');
Route::post('/cashier/pos-sale', [POSController::class, 'sale'])->name('cashier.pos-sale');
Route::get('/api/products/lookup', [POSController::class, 'lookup'])->name('products.lookup');
Route::get('/api/v1/products/lookup', [POSController::class, 'lookup']);

