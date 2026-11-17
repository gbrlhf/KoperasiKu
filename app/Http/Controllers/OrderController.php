<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TransactionService;

class OrderController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Endpoint Checkout Pesanan (Online Pickup / POS)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'payment_choice' => 'required|in:CASH,BANK_TRANSFER,CREDIT',
            'channel' => 'nullable|in:ONLINE_PICKUP,POS',
            'member_id' => 'nullable|uuid',
            'idempotency_key' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $channel = $validated['channel'] ?? 'ONLINE_PICKUP';
            $order = $this->transactionService->checkout($validated, $channel);

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil diproses.',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * Endpoint Konfirmasi Pickup / Serah Terima Barang oleh Kasir
     */
    public function confirmPickup(string $id)
    {
        try {
            $result = $this->transactionService->confirmPickup($id);

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil diselesaikan (Pickup dikonfirmasi).',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}