<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ReceivableController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Autentikasi berbasis database users & user_roles
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/switch-role', [AuthController::class, 'switchRole']);
    });

    // Rute Anggota / Pembeli (Checkout & data milik sendiri)
    Route::middleware(['auth', 'role:MEMBER,CASHIER,MANAGER'])->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/my', [OrderController::class, 'myOrders']);
        Route::get('/members/me/credit', [ReceivableController::class, 'myCredit']);
        Route::get('/members/me/receivables', [ReceivableController::class, 'myReceivables']);
    });

    // Rute khusus Kasir & Manajer (Operasional POS, Pickup, Verifikasi Transfer & Cicilan Piutang)
    Route::middleware(['auth', 'role:CASHIER,MANAGER'])->group(function () {
        Route::get('/products/lookup', [POSController::class, 'lookup']);
        Route::get('/cashier/orders', [OrderController::class, 'cashierOrders']);
        Route::post('/orders/{id}/payments', [OrderController::class, 'recordPayment']);
        Route::post('/payments/{id}/verify', [OrderController::class, 'verifyPayment']);
        Route::post('/orders/{id}/confirm-pickup', [OrderController::class, 'confirmPickup']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

        Route::post('/pos/sale', [POSController::class, 'sale']);
        Route::post('/pos/sales', [POSController::class, 'sale']);

        Route::get('/cashier/receivables', [ReceivableController::class, 'cashierReceivables']);
        Route::post('/receivables/payments', [ReceivableController::class, 'pay']);
    });

    // Rute khusus Manajer (Kebijakan limit kredit global, portofolio piutang & Master Data PIC C)
    Route::middleware(['auth', 'role:MANAGER'])->group(function () {
        Route::get('/credit-policy', [ReceivableController::class, 'getCreditPolicy']);
        Route::patch('/credit-policy', [ReceivableController::class, 'updateCreditPolicy']);
        Route::patch('/members/{id}/credit-status', [ReceivableController::class, 'updateMemberCreditStatus']);
        Route::get('/manager/receivables', [ReceivableController::class, 'managerReceivables']);
        Route::get('/manager/receivables/aging', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'aging']);

        // Analitik, Prediksi Permintaan (7 / 30 Hari) & Laporan Keuangan
        Route::get('/analytics/forecasts', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'forecasts']);
        Route::get('/analytics/summary', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'summary']);
        Route::get('/analytics/top-products', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'topProducts']);
        Route::get('/analytics/categories', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'categories']);
        Route::get('/analytics/trend', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'trend']);
        Route::get('/reports/financial', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'financial']);
        Route::get('/reports/financial/export', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'export']);

        // Produk & Histori Harga
        Route::get('/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
        Route::get('/products/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);
        Route::post('/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'store']);
        Route::post('/products/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'update']);
        Route::put('/products/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'update']);
        Route::patch('/products/{id}', [\App\Http\Controllers\Api\V1\ProductController::class, 'update']);
        Route::post('/products/{id}/price', [\App\Http\Controllers\Api\V1\ProductController::class, 'updatePrice']);

        // Pemasok (Suppliers)
        Route::get('/suppliers', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'suppliers']);
        Route::post('/suppliers', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'storeSupplier']);
        Route::patch('/suppliers/{id}', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'updateSupplier']);
        Route::put('/suppliers/{id}', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'updateSupplier']);

        // Anggota (Members)
        Route::get('/members', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'members']);
        Route::post('/members', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'storeMember']);
        Route::patch('/members/{id}', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'updateMember']);
        Route::put('/members/{id}', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'updateMember']);

        // Penerimaan Pembelian (WAC) & Penyesuaian Stok
        Route::get('/purchase-receipts', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'receipts']);
        Route::post('/purchase-receipts', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'storeReceipt']);
        Route::get('/inventory/receipts', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'receipts']);
        Route::post('/inventory/receipts', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'storeReceipt']);
        Route::get('/inventory/movements', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'stockMovements']);
        Route::post('/inventory/adjustments', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'storeAdjustment']);

        // Biaya Operasional (Operating Expenses)
        Route::get('/operating-expenses', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'expenses']);
        Route::post('/operating-expenses', [\App\Http\Controllers\Api\V1\MasterDataController::class, 'storeExpense']);

        // Impor Excel/CSV (Staging, Preview, Validasi & Commit)
        Route::get('/imports', [\App\Http\Controllers\Api\V1\ImportController::class, 'index']);
        Route::post('/imports', [\App\Http\Controllers\Api\V1\ImportController::class, 'upload']);
        Route::post('/imports/upload', [\App\Http\Controllers\Api\V1\ImportController::class, 'upload']);
        Route::get('/imports/{id}', [\App\Http\Controllers\Api\V1\ImportController::class, 'show']);
        Route::get('/imports/{id}/preview', [\App\Http\Controllers\Api\V1\ImportController::class, 'show']);
        Route::post('/imports/{id}/validate', [\App\Http\Controllers\Api\V1\ImportController::class, 'validateBatch']);
        Route::post('/imports/{id}/commit', [\App\Http\Controllers\Api\V1\ImportController::class, 'commit']);
    });
});
