<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TransactionService;

class POSController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Menangani penjualan langsung kasir (POS)
     * Endpoint: POST /api/v1/pos/sale
     */
    public function sale(Request $request)
    {
        try {
            // Validasi input data dari request kasir
            $validated = $request->validate([
                'member_id' => 'nullable|uuid',
                'payment_choice' => 'required|string|in:CASH,BANK_TRANSFER,CREDIT',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            // Eksekusi logika bisnis lewat TransactionService
            $result = $this->transactionService->posSale($validated);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi POS berhasil diselesaikan.',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 500) ? $e->getCode() : 422;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}