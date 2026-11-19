<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ReceivableController;
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
// 1. Umum & Autentikasi Nyata (Database Users + User Roles)
// --------------------------------------------------------------------------
Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
Route::post('/switch-account', [AuthController::class, 'switchAccount'])->middleware('auth')->name('switch-account');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/logout', fn () => redirect()->route('landing'));
Route::post('/mock-switch-role', [AuthController::class, 'switchRole'])
    ->middleware('auth')
    ->name('mock.switch-role');

// --------------------------------------------------------------------------
// 2. Portal Pelanggan / Anggota (Disatukan di Landing Page + Drawer Profil)
// --------------------------------------------------------------------------
Route::prefix('member')->name('member.')->middleware(['auth', 'role:MEMBER,CASHIER,MANAGER'])->group(function () {
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
Route::prefix('cashier')->name('cashier.')->middleware(['auth', 'role:CASHIER,MANAGER'])->group(function () {
    Route::get('/', [CashierController::class, 'index'])->name('index');
    Route::get('/pos', [CashierController::class, 'index'])->name('pos');
    Route::get('/stok', [CashierController::class, 'stok'])->name('stok');
    Route::get('/antrian', [CashierController::class, 'antrian'])->name('antrian');
    Route::get('/piutang', [CashierController::class, 'piutang'])->name('piutang');
    Route::get('/pickup-lookup', [CashierController::class, 'pickupLookup'])->name('pickup-lookup');
});

// --------------------------------------------------------------------------
// 4. Portal Manajer Eksekutif (Analitik Penjualan Bulan Ini, Laba & Piutang)
// --------------------------------------------------------------------------
Route::prefix('manager')->name('manager.')->middleware(['auth', 'role:MANAGER'])->group(function () {
    Route::view('/', 'manager.portal-v2', ['initialPage' => 'home', 'initialTab' => 0])->name('index');
    Route::view('/dashboard', 'manager.portal-v2', ['initialPage' => 'home', 'initialTab' => 0])->name('dashboard');
    Route::view('/kredit', 'manager.portal-v2', ['initialPage' => 'ar', 'initialTab' => 0])->name('kredit');
    Route::view('/piutang', 'manager.portal-v2', ['initialPage' => 'ar', 'initialTab' => 0])->name('piutang');
    Route::view('/produk', 'manager.portal-v2', ['initialPage' => 'data', 'initialTab' => 0])->name('produk');
    Route::view('/import', 'manager.portal-v2', ['initialPage' => 'data', 'initialTab' => 3])->name('import');
    Route::view('/laporan', 'manager.portal-v2', ['initialPage' => 'rep', 'initialTab' => 0])->name('laporan');
    Route::view('/transaksi', 'manager.portal-v2', ['initialPage' => 'ops', 'initialTab' => 0])->name('transaksi');
    Route::view('/sistem', 'manager.portal-v2', ['initialPage' => 'sys', 'initialTab' => 0])->name('sistem');
});

// --------------------------------------------------------------------------
// 5. Integrasi Backend Transaksi Langsung (Checkout Storefront, POS & Pickup)
// --------------------------------------------------------------------------
Route::post('/checkout', [OrderController::class, 'store'])
    ->middleware(['auth', 'role:MEMBER,CASHIER,MANAGER'])
    ->name('checkout.submit');
Route::post('/cashier/confirm-pickup', [OrderController::class, 'confirmPickup'])
    ->middleware(['auth', 'role:CASHIER,MANAGER'])
    ->name('cashier.confirm-pickup');
Route::post('/cashier/pos-sale', [POSController::class, 'sale'])
    ->middleware(['auth', 'role:CASHIER,MANAGER'])
    ->name('cashier.pos-sale');
Route::get('/api/products/lookup', [POSController::class, 'lookup'])
    ->middleware(['auth', 'role:CASHIER,MANAGER'])
    ->name('products.lookup');
Route::get('/api/v1/products/lookup', [POSController::class, 'lookup'])
    ->middleware(['auth', 'role:CASHIER,MANAGER']);

// --------------------------------------------------------------------------
// 6. Export & Import Excel/CSV Portal Manajer
// --------------------------------------------------------------------------
Route::middleware(['auth', 'role:MANAGER'])->group(function () {
    Route::get('/manager/excel/template', [\App\Http\Controllers\ManagerExcelController::class, 'downloadTemplate'])->name('manager.excel.template');
    Route::get('/manager/excel/export', [\App\Http\Controllers\ManagerExcelController::class, 'exportData'])->name('manager.excel.export');
    Route::get('/manager/excel/errors/{id}', [\App\Http\Controllers\ManagerExcelController::class, 'exportErrors'])->name('manager.excel.errors');
    Route::post('/api/v1/imports', [\App\Http\Controllers\ManagerExcelController::class, 'uploadPreview']);
    Route::get('/api/v1/imports/{id}/preview', [\App\Http\Controllers\ManagerExcelController::class, 'getPreview']);
    Route::post('/api/v1/imports/{id}/commit', [\App\Http\Controllers\ManagerExcelController::class, 'commitBatch']);
});
