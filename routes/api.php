<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ReceivableController;

Route::prefix('v1')->group(function () {
    
    // Rute publik atau yang membutuhkan autentikasi dasar untuk pemesanan (Member / Pelanggan)
    Route::middleware(['auth:sanctum'])->group(function () {
        // Modul Pesanan (Checkout & Pickup)
        Route::post('/orders', [OrderController::class, 'store']);
        Route::post('/orders/{id}/confirm-pickup', [OrderController::class, 'confirmPickup']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']); // Tambahan untuk pembatalan pesanan
    });

    // Rute khusus Kasir & Manajer (POS dan Pembayaran Cicilan Piutang)
    Route::middleware(['auth:sanctum', 'role:CASHIER,MANAGER'])->group(function () {
        // Penjualan Langsung POS
        Route::post('/pos/sale', [POSController::class, 'sale']);
        
        // Pencatatan Pembayaran Cicilan / Pelunasan Piutang
        Route::post('/receivables/payments', [ReceivableController::class, 'pay']);
    });

});