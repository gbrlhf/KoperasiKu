<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(protected TransactionService $transactionService) {}

    /**
     * Endpoint Checkout Pesanan Online Pickup (/checkout atau POST /api/v1/orders)
     * Ownership: member_id wajib diambil dari user login, dilarang mempercayai member_id dari request body.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'payment_choice' => 'required_without:payment_method|string|in:CASH,BANK_TRANSFER,CREDIT',
                'payment_method' => 'required_without:payment_choice|string|in:CASH,BANK_TRANSFER,CREDIT',
                'idempotency_key' => 'nullable|string|max:100',
                'expected_total' => 'nullable|numeric|min:0.01',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|string',
                'items.*.quantity' => 'required|integer|min:1',
            ]);

            $user = $request->user();
            $member = $user?->member;
            if (!$member) {
                throw new BusinessRuleException('Akun Anda tidak tertaut dengan profil anggota koperasi.', 403, 'FORBIDDEN');
            }

            // Tolak apabila client mencoba menyisipkan member_id milik orang lain
            if ($request->filled('member_id') && (string) $request->input('member_id') !== (string) $member->id) {
                throw new BusinessRuleException('Tidak diperbolehkan membuat pesanan atas nama anggota lain.', 403, 'FORBIDDEN');
            }

            $payload = [
                'member_id' => (string) $member->id,
                'payment_choice' => strtoupper((string) ($validated['payment_method'] ?? $validated['payment_choice'])),
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'items' => $validated['items'],
                'expected_total' => $validated['expected_total'] ?? null,
            ];

            $order = $this->transactionService->checkout($payload, 'ONLINE_PICKUP', (string) $user->id);

            $data = [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'pickup_code' => $order->pickup_code,
                'status' => $order->status,
                'payment_choice' => $order->payment_choice,
                'total_amount' => (float) $order->total_amount,
                'reserved_stock' => true,
            ];
            if ($order->wasIdempotentReplay) {
                $data['idempotent'] = true;
            }

            return response()->json([
                'success' => true,
                'message' => $order->wasIdempotentReplay
                    ? 'Permintaan checkout sebelumnya dikembalikan (Idempotent).'
                    : 'Checkout berhasil diproses.',
                'data' => $data,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Checkout error', ['error' => $e->getMessage(), 'user_id' => $request->user()?->id]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan saat memproses pesanan. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Pesanan milik anggota yang sedang login (GET /api/v1/orders/my)
     */
    public function myOrders(Request $request)
    {
        $member = $request->user()?->member;
        if (!$member) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $orders = Order::with(['items:id,order_id,product_id,quantity,unit_price_snapshot'])
            ->where('member_id', $member->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Daftar antrian & riwayat order untuk Kasir/Manajer (GET /api/v1/cashier/orders)
     */
    public function cashierOrders(Request $request)
    {
        $statusFilter = strtoupper(trim((string) $request->query('status', '')));
        $channelFilter = strtoupper(trim((string) $request->query('channel', '')));
        $search = trim((string) $request->query('search', ''));

        $query = Order::query()
            ->with([
                'items:id,order_id,product_id,quantity,unit_price_snapshot,hpp_snapshot',
                'items.product:id,sku,name,unit,current_cost',
                'member:id,full_name,employee_number,member_number,department',
                'payments:id,order_id,method,amount,status,external_reference,verified_at',
                'receivable:id,order_id,original_amount,outstanding_amount,due_date,status',
            ])
            ->orderByDesc('created_at');

        if ($statusFilter === 'ALL') {
            // Tampilkan seluruh status pesanan (aktif, selesai, dan dibatalkan)
        } elseif ($statusFilter === 'ACTIVE' || $statusFilter === '') {
            $query->whereIn('status', ['PENDING_PAYMENT', 'PENDING_PICKUP', 'READY_FOR_PICKUP']);
        } else {
            $query->where('status', $statusFilter);
        }

        if (in_array($channelFilter, ['POS', 'ONLINE_PICKUP'], true)) {
            $query->where('channel', $channelFilter);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('order_no', 'ilike', $like)
                    ->orWhere('pickup_code', 'ilike', $like)
                    ->orWhereHas('member', function ($mq) use ($like) {
                        $mq->where('full_name', 'ilike', $like)
                            ->orWhere('employee_number', 'ilike', $like)
                            ->orWhere('member_number', 'ilike', $like);
                    })
                    ->orWhereHas('items.product', function ($pq) use ($like) {
                        $pq->where('name', 'ilike', $like)
                            ->orWhere('sku', 'ilike', $like);
                    });
            });
        }

        $counts = Order::query()
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('PENDING_PAYMENT', 'PENDING_PICKUP', 'READY_FOR_PICKUP') THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'PENDING_PAYMENT' THEN 1 ELSE 0 END) AS pending_payment,
                SUM(CASE WHEN status IN ('PENDING_PICKUP', 'READY_FOR_PICKUP') THEN 1 ELSE 0 END) AS ready_pickup,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
            ")
            ->first();

        return response()->json([
            'success' => true,
            'data' => $query->limit(150)->get(),
            'meta' => [
                'counts' => [
                    'total' => (int) ($counts->total ?? 0),
                    'active' => (int) ($counts->active ?? 0),
                    'pending_payment' => (int) ($counts->pending_payment ?? 0),
                    'ready_pickup' => (int) ($counts->ready_pickup ?? 0),
                    'completed' => (int) ($counts->completed ?? 0),
                    'cancelled' => (int) ($counts->cancelled ?? 0),
                ],
            ],
        ]);
    }

    /**
     * Catat pembayaran order sebelum/saat pickup (POST /api/v1/orders/{id}/payments)
     */
    public function recordPayment(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'method' => 'required|string|in:CASH,BANK_TRANSFER',
                'amount' => 'required|numeric|min:0.01',
                'external_reference' => 'nullable|string|max:100',
                'idempotency_key' => 'nullable|string|max:100',
            ]);

            $user = $request->user();
            $result = $this->transactionService->recordOrderPayment(
                $id,
                $validated,
                (string) $user->id,
                $user->member?->id ? (string) $user->member->id : null
            );

            return response()->json([
                'success' => true,
                'message' => $result['status'] === 'VERIFIED'
                    ? 'Pembayaran pesanan berhasil dicatat dan terverifikasi.'
                    : 'Bukti transfer pesanan tercatat dan menunggu verifikasi petugas.',
                'data' => $result,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Record order payment error', ['order_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan saat mencatat pembayaran pesanan.',
            ], 500);
        }
    }

    /**
     * Verifikasi atau tolak pembayaran transfer (POST /api/v1/payments/{id}/verify)
     */
    public function verifyPayment(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'action' => 'nullable|string|in:VERIFY,REJECT',
                'idempotency_key' => 'nullable|string|max:100',
            ]);

            $user = $request->user();
            $action = $validated['action'] ?? 'VERIFY';

            $result = $this->transactionService->verifyPayment(
                $id,
                $action,
                (string) $user->id,
                $user->member?->id ? (string) $user->member->id : null,
                $validated['idempotency_key'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => $result['status'] === 'VERIFIED'
                    ? 'Pembayaran berhasil diverifikasi.'
                    : 'Pembayaran telah ditolak.',
                'data' => $result,
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Verify payment error', ['payment_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan saat memverifikasi pembayaran.',
            ], 500);
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
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Kode Pickup atau Order ID wajib disertakan.'
            ], 422);
        }

        try {
            $validated = $request->validate([
                'pickup_code' => 'required|string|max:100',
                'handover_confirmed' => 'required|accepted',
                'idempotency_key' => 'nullable|string|max:100',
            ]);
            $user = $request->user();
            $idempotencyKey = $request->input('idempotency_key');

            $result = $this->transactionService->confirmPickup(
                (string) $orderId,
                $user?->id ? (string) $user->id : null,
                $user?->member?->id ? (string) $user->member->id : null,
                $idempotencyKey ? (string) $idempotencyKey : null,
                $validated['pickup_code'],
                true
            );

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil diselesaikan (Pickup dikonfirmasi).',
                'data' => $result
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage()
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Confirm pickup error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan saat memproses serah terima pickup.'
            ], 500);
        }
    }

    /**
     * Pembatalan pesanan sebelum serah terima (POST /api/v1/orders/{id}/cancel)
     */
    public function cancel(Request $request, string $id)
    {
        try {
            $result = $this->transactionService->cancelOrder($id, $request->user()?->id ? (string) $request->user()->id : null);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ], 200);
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->errorCode,
                'message' => $e->getMessage(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Log::error('Cancel order error', ['order_id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Terjadi kesalahan saat membatalkan pesanan.',
            ], 500);
        }
    }
}
