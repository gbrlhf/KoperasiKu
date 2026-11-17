<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\TransactionService;
use Illuminate\Http\Request;

class POSController extends Controller
{
    protected $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Menangani penjualan langsung kasir (POS)
     * Endpoint: POST /api/v1/pos/sale atau POST /cashier/pos-sale
     */
    public function sale(Request $request)
    {
        try {
            $validated = $request->validate([
                'member_id' => 'nullable|string',
                'payment_choice' => 'required|string|in:CASH,BANK_TRANSFER,CREDIT',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            if (empty($validated['member_id']) && $validated['payment_choice'] === 'CREDIT') {
                $validated['member_id'] = '00000000-0000-0000-0000-000000000001';
            }

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

    /**
     * Pencarian produk via barcode / SKU scanner
     * Endpoint: GET /api/v1/products/lookup?barcode=...
     */
    public function lookup(Request $request)
    {
        $barcode = $request->query('barcode') ?? $request->query('q');
        if (!$barcode) {
            return response()->json(['success' => false, 'message' => 'Parameter barcode wajib diisi.'], 422);
        }

        $product = Product::where('barcode', $barcode)
            ->orWhere('sku', $barcode)
            ->with('inventory')
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Barang dengan barcode/SKU tersebut tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'price' => $product->selling_price,
                'unit' => $product->unit,
                'available_stock' => $product->inventory ? ($product->inventory->on_hand - $product->inventory->reserved) : 0,
            ]
        ]);
    }
}
