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
            'member_id' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if (empty($validated['member_id']) && $validated['payment_choice'] === 'CREDIT') {
            $validated['member_id'] = '00000000-0000-0000-0000-000000000001'; // Default Anggota Terverifikasi (Ahmad Fauzi)
        }

        try {
            $channel = $validated['channel'] ?? 'ONLINE_PICKUP';
            $order = $this->transactionService->checkout($validated, $channel);

            return response()->json([
                'success' => true,
                'message' => 'Checkout berhasil diproses.',
                'data' => [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'pickup_code' => $order->pickup_code,
                    'status' => $order->status,
                    'payment_choice' => $order->payment_choice,
                    'total_amount' => $order->total_amount,
                ]
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
    public function confirmPickup(Request $request, ?string $id = null)
    {
        $orderId = $id ?? $request->input('order_id') ?? $request->input('pickup_code');

        if (!$orderId) {
            return response()->json([
                'success' => false,
                'message' => 'Kode Pickup atau Order ID wajib disertakan.'
            ], 422);
        }

        try {
            $result = $this->transactionService->confirmPickup($orderId);

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
