<?php

use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Katalog Produk (dapat dilihat semua role; HPP current_cost otomatis tersembunyi untuk non-MANAGER)
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::get('/categories', [MasterDataController::class, 'categories']);
    Route::get('/members', [MasterDataController::class, 'members']);

    // Area Manajemen Master Data & Import (Khusus MANAGER)
    Route::middleware('role:MANAGER')->group(function () {
        // Produk & Harga
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::post('/products/{id}/price', [ProductController::class, 'updatePrice']);

        // Kategori, Anggota, Supplier
        Route::post('/categories', [MasterDataController::class, 'storeCategory']);
        Route::post('/members', [MasterDataController::class, 'storeMember']);
        Route::get('/suppliers', [MasterDataController::class, 'suppliers']);
        Route::post('/suppliers', [MasterDataController::class, 'storeSupplier']);

        // Penerimaan Barang Pemasok & Biaya Operasional
        Route::get('/purchase-receipts', [MasterDataController::class, 'receipts']);
        Route::post('/purchase-receipts', [MasterDataController::class, 'storeReceipt']);
        Route::get('/operating-expenses', [MasterDataController::class, 'expenses']);
        Route::post('/operating-expenses', [MasterDataController::class, 'storeExpense']);

        // Import Data Historis Excel/CSV (Upload -> Preview/Revalidate -> Idempotent Commit)
        Route::get('/imports', [ImportController::class, 'index']);
        Route::post('/imports/upload', [ImportController::class, 'upload']);
        Route::get('/imports/{id}', [ImportController::class, 'show']);
        Route::post('/imports/{id}/validate', [ImportController::class, 'revalidate']);
        Route::post('/imports/{id}/commit', [ImportController::class, 'commit']);
    });
});

Route::prefix('v1/ml')->group(function () {
    Route::get('/health', [\App\Http\Controllers\MlController::class, 'health']);
    Route::post('/demand-forecast', [\App\Http\Controllers\MlController::class, 'forecastDemand']);
    Route::post('/credit-risk', [\App\Http\Controllers\MlController::class, 'creditRisk']);
});