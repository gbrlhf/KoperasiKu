<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TransactionService;

class ReceivableController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Menangani pencatatan pembayaran cicilan/pelunasan piutang anggota
     * Endpoint: POST /api/v1/receivables/payments
     */
    public function pay(Request $request)
    {
        try {
            // Validasi input pembayaran cicilan
            $validated = $request->validate([
                'member_id' => 'required|uuid',
                'method' => 'required|string|in:CASH,BANK_TRANSFER',
                'amount' => 'required|numeric|min:0.01',
                'allocations' => 'required|array|min:1',
                'allocations.*.receivable_id' => 'required',
                'allocations.*.amount' => 'required|numeric|min:0.01',
            ]);

            // Eksekusi logika pembayaran piutang lewat TransactionService
            $result = $this->transactionService->payReceivable($validated);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() <= 500) ? $e->getCode() : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}