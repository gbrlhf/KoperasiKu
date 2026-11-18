<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ReceivableController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Autentikasi berbasis database users & user_roles
    Route::post('/auth/login', [AuthController::class, 'login']);

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

    // Rute khusus Manajer (Kebijakan limit kredit global & portofolio piutang)
    Route::middleware(['auth', 'role:MANAGER'])->group(function () {
        Route::get('/credit-policy', [ReceivableController::class, 'getCreditPolicy']);
        Route::patch('/credit-policy', [ReceivableController::class, 'updateCreditPolicy']);
        Route::patch('/members/{id}/credit-status', [ReceivableController::class, 'updateMemberCreditStatus']);
        Route::get('/manager/receivables', [ReceivableController::class, 'managerReceivables']);
    });
});
