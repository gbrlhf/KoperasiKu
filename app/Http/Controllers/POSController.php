<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Member;
use App\Models\Product;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class POSController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    /**
     * Menangani penjualan langsung kasir (POS)
     * Endpoint: POST /api/v1/pos/sale atau POST /api/v1/pos/sales atau POST /cashier/pos-sale
     */
    public function sale(Request $request)
    {
        try {
            $validated = $request->validate([
                'member_id' => 'nullable|string|max:100',
                'payment_choice' => 'required_without:payment_method|string|in:CASH,BANK_TRANSFER,CREDIT',
                'payment_method' => 'required_without:payment_choice|string|in:CASH,BANK_TRANSFER,CREDIT',
                'external_reference' => 'nullable|string|max:100',
                'transfer_verified' => 'nullable|boolean',
                'expected_total' => 'nullable|numeric|min:0.01',
                'cash_received' => 'nullable|numeric|min:0',
                'idempotency_key' => 'nullable|string|max:100',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            $memberId = null;
            if (!empty($validated['member_id'])) {
                $rawMember = trim((string) $validated['member_id']);
                $member = Str::isUuid($rawMember)
                    ? Member::where('id', $rawMember)->first()
                    : Member::whereRaw('UPPER(employee_number) = ?', [strtoupper($rawMember)])
                        ->orWhereRaw('UPPER(member_number) = ?', [strtoupper($rawMember)])
                        ->first();

                if (!$member) {
                    throw new BusinessRuleException('Anggota koperasi tidak ditemukan.', 404, 'MEMBER_NOT_FOUND');
                }
                $memberId = (string) $member->id;
            }

            $user = $request->user();
            $payload = [
                'member_id' => $memberId,
                'payment_choice' => strtoupper((string) ($validated['payment_method'] ?? $validated['payment_choice'])),
                'external_reference' => $validated['external_reference'] ?? null,
                'transfer_verified' => $request->boolean('transfer_verified'),
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'items' => $validated['items'],
                'expected_total' => $validated['expected_total'] ?? null,
                'cash_received' => $validated['cash_received'] ?? null,
            ];

            $result = $this->transactionService->posSale(
                $payload,
                $user?->id ? (string) $user->id : null,
                $user?->member?->id ? (string) $user->member->id : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi POS berhasil diselesaikan.',
                'data' => $result
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage()
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('POS sale error', ['error' => $e->getMessage(), 'user_id' => $request->user()?->id]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan saat memproses transaksi POS.'
            ], 500);
        }
    }

    /**
     * Pencarian produk via barcode / SKU scanner
     * Endpoint: GET /api/v1/products/lookup?barcode=...
     */
    public function lookup(Request $request)
    {
        $input = $request->validate(['barcode' => 'nullable|string|max:150', 'q' => 'nullable|string|max:150']);
        $barcode = trim((string) ($input['barcode'] ?? $input['q'] ?? ''));
        if ($barcode === '') {
            return response()->json(['success' => false, 'message' => 'Parameter barcode wajib diisi.'], 422);
        }

        $product = Product::where('is_active', true)->where(fn ($query) => $query->whereRaw('UPPER(barcode) = ?', [strtoupper($barcode)])->orWhereRaw('UPPER(sku) = ?', [strtoupper($barcode)]))
            ->with('inventory')
            ->limit(2)->get();
        if ($product->count() > 1) {
            return response()->json(['success' => false, 'message' => 'Kode ambigu. Gunakan SKU unik atau hubungi manajer.'], 409);
        }
        $product = $product->first();

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
                'available_stock' => $product->inventory ? max(0, (int) $product->inventory->on_hand - (int) $product->inventory->reserved) : 0,
            ]
        ]);
    }
}
