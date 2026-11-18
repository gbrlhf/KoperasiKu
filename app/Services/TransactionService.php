<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\CreditHold;
use App\Models\CreditPolicy;
use App\Models\InventoryBalance;
use App\Models\Member;
use App\Models\MemberCreditAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\ReceivablePaymentAllocation;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransactionService
{
    private AuditService $audit;
    private IdempotencyService $idempotency;

    public function __construct(?AuditService $audit = null, ?IdempotencyService $idempotency = null)
    {
        $this->audit = $audit ?? app(AuditService::class);
        $this->idempotency = $idempotency ?? app(IdempotencyService::class);
    }

    public function checkout(array $data, string $channel = 'ONLINE_PICKUP', ?string $actorUserId = null): Order
    {
        $memberId = $data['member_id'] ?? null;
        $paymentChoice = strtoupper((string) $data['payment_choice']);
        $items = $data['items'];
        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);

        $canonicalPayload = [
            'channel' => strtoupper(trim($channel)),
            'member_id' => $memberId ? strtolower((string) $memberId) : null,
            'payment_choice' => $paymentChoice,
            'items' => $this->canonicalizeItems($items),
        ];

        $execution = $this->idempotency->run(
            $idempotencyKey,
            'CHECKOUT',
            $actorUserId,
            null,
            $canonicalPayload,
            function () use ($memberId, $paymentChoice, $items, $channel, $actorUserId, $idempotencyKey): array {
                if ($idempotencyKey !== null) {
                    $legacyOrder = Order::where('idempotency_key', $idempotencyKey)->first();
                    if ($legacyOrder) {
                        if ($memberId && $legacyOrder->member_id && $legacyOrder->member_id !== $memberId) {
                            throw new BusinessRuleException('Idempotency key sudah digunakan oleh akun lain.', 403, 'FORBIDDEN');
                        }
                        throw new BusinessRuleException('Idempotency key sudah digunakan oleh transaksi lain.', 409, 'IDEMPOTENCY_CONFLICT');
                    }
                }

                if (!$memberId) {
                    throw new BusinessRuleException('Checkout pesanan online memerlukan akun anggota aktif.', 422, 'MEMBER_REQUIRED');
                }

                $member = Member::where('id', $memberId)->first();
                if (!$member || strtoupper((string) $member->status) !== 'ACTIVE') {
                    throw new BusinessRuleException('Keanggotaan tidak ditemukan atau tidak aktif.', 422, 'MEMBER_INACTIVE');
                }

                $totalAmount = 0.0;
                $orderItemsData = [];

                // Urutkan product_id agar menghindari deadlock saat locking banyak item secara paralel
                $sortedItems = collect($items)->sortBy(fn ($i) => (string) $i['product_id'])->values()->all();

                foreach ($sortedItems as $item) {
                    // Urutan lock kanonik: products -> inventory_balances agar pembacaan current_cost aman dari balapan WAC
                    $product = $this->findProduct((string) $item['product_id'], true);
                    if (!$product) {
                        throw new BusinessRuleException("Produk tidak ditemukan di master katalog: {$item['product_id']}", 404, 'PRODUCT_NOT_FOUND');
                    }
                    if (isset($product->is_active) && !$product->is_active) {
                        throw new BusinessRuleException("Produk sedang nonaktif: {$product->name}", 422, 'PRODUCT_INACTIVE');
                    }

                    $qty = (int) $item['quantity'];
                    if ($qty <= 0) {
                        throw new BusinessRuleException('Kuantitas barang harus lebih dari 0.', 422, 'INVALID_QUANTITY');
                    }

                    $inventory = InventoryBalance::where('product_id', $product->id)->lockForUpdate()->first();
                    if (!$inventory) {
                        throw new BusinessRuleException("Stok belum tersedia untuk produk: {$product->name}", 422, 'INSUFFICIENT_STOCK');
                    }

                    $available = (int) $inventory->on_hand - (int) $inventory->reserved;
                    if ($available < $qty) {
                        throw new BusinessRuleException("Stok tersedia tidak mencukupi untuk produk: {$product->name}", 422, 'INSUFFICIENT_STOCK');
                    }

                    $subtotal = (float) $product->selling_price * $qty;
                    $totalAmount += $subtotal;

                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        // Harga jual di-snapshot saat checkout; HPP disnapshot final saat serah terima (COMPLETED)
                        'unit_price_snapshot' => (float) $product->selling_price,
                        'hpp_snapshot' => null,
                    ];
                }

                if ($paymentChoice === 'CREDIT') {
                    $this->validateAndLockMemberCredit((string) $memberId, $totalAmount);
                }

                $pickupCode = $this->generateUniquePickupCode();
                $orderNo = 'ORD-' . CarbonImmutable::now('Asia/Jakarta')->format('Ymd') . '-' . strtoupper(Str::random(5));

                $order = Order::create([
                    'member_id' => $memberId,
                    'channel' => $channel,
                    'status' => ($paymentChoice === 'CREDIT') ? 'PENDING_PICKUP' : 'PENDING_PAYMENT',
                    'payment_choice' => $paymentChoice,
                    'order_no' => $orderNo,
                    'pickup_code' => $pickupCode,
                    'total_amount' => $totalAmount,
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($orderItemsData as $orderItem) {
                    OrderItem::create(array_merge($orderItem, ['order_id' => $order->id]));
                    InventoryBalance::where('product_id', $orderItem['product_id'])->increment('reserved', $orderItem['quantity']);

                    StockReservation::create([
                        'order_id' => $order->id,
                        'product_id' => $orderItem['product_id'],
                        'quantity' => $orderItem['quantity'],
                    ]);
                }

                if ($paymentChoice === 'CREDIT') {
                    CreditHold::create([
                        'order_id' => $order->id,
                        'member_id' => $memberId,
                        'amount' => $totalAmount,
                        'status' => 'ACTIVE',
                    ]);
                }

                $this->audit->record(
                    'CHECKOUT_CREATED',
                    'Order',
                    (string) $order->id,
                    null,
                    [
                        'order_no' => $order->order_no,
                        'member_id' => $memberId,
                        'payment_choice' => $paymentChoice,
                        'status' => $order->status,
                        'total_amount' => $totalAmount,
                    ],
                    $actorUserId
                );

                return [
                    'status' => 201,
                    'target_resource_id' => (string) $order->id,
                    'body' => [
                        'order_id' => (string) $order->id,
                        'order_no' => $order->order_no,
                        'pickup_code' => $order->pickup_code,
                        'status' => $order->status,
                        'payment_choice' => $order->payment_choice,
                        'total_amount' => (float) $order->total_amount,
                    ],
                ];
            }
        );

        /** @var Order $order */
        $order = Order::findOrFail($execution['body']['order_id']);
        $order->wasIdempotentReplay = $execution['replayed'];

        return $order;
    }

    public function recordOrderPayment(string $orderIdentifier, array $data, string $actorUserId, ?string $actorMemberId = null): array
    {
        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);
        $method = strtoupper((string) $data['method']);
        $amount = (float) $data['amount'];
        $externalRef = isset($data['external_reference']) ? trim((string) $data['external_reference']) : null;

        $resolvedOrder = $this->findOrderByIdentifier($orderIdentifier);
        $targetResourceId = $resolvedOrder ? (string) $resolvedOrder->id : ('unresolved:' . strtolower(trim($orderIdentifier)));

        $canonicalPayload = [
            'order_id' => $targetResourceId,
            'method' => $method,
            'amount' => round($amount, 2),
            'external_reference' => $externalRef ?: null,
        ];

        $execution = $this->idempotency->run(
            $idempotencyKey,
            'ORDER_PAYMENT',
            $actorUserId,
            $targetResourceId,
            $canonicalPayload,
            function () use ($orderIdentifier, $actorUserId, $actorMemberId, $idempotencyKey, $method, $amount, $externalRef): array {
                if ($idempotencyKey !== null && Payment::where('idempotency_key', $idempotencyKey)->exists()) {
                    throw new BusinessRuleException('Idempotency key sudah digunakan oleh pembayaran lain.', 409, 'IDEMPOTENCY_CONFLICT');
                }

                $order = $this->findOrderForUpdate($orderIdentifier);
                if (!$order) {
                    throw new BusinessRuleException("Pesanan '{$orderIdentifier}' tidak ditemukan.", 404, 'ORDER_NOT_FOUND');
                }

                if (in_array($order->status, ['COMPLETED', 'CANCELLED'], true)) {
                    throw new BusinessRuleException("Pesanan berstatus {$order->status} tidak dapat menerima pembayaran order baru.", 400, 'INVALID_ORDER_STATUS');
                }

                if ($amount <= 0) {
                    throw new BusinessRuleException('Nominal pembayaran harus lebih besar dari nol.', 422, 'INVALID_PAYMENT');
                }

                if ($method === 'CASH' && $order->member_id && $actorMemberId && $order->member_id === $actorMemberId) {
                    throw new BusinessRuleException('Petugas tidak dapat mengonfirmasi pembayaran tunai atas pesanannya sendiri.', 403, 'SELF_PAYMENT_FORBIDDEN');
                }

                if ($externalRef !== null && $externalRef !== '') {
                    if (Payment::where('external_reference', $externalRef)->exists()) {
                        throw new BusinessRuleException('Referensi transfer sudah pernah tercatat (Duplikasi).', 422, 'DUPLICATE_PAYMENT');
                    }
                }

                $alreadyAccounted = (float) Payment::where('order_id', $order->id)
                    ->whereIn('status', ['VERIFIED', 'PENDING_VERIFICATION'])
                    ->sum('amount');

                if (($alreadyAccounted + $amount) > (float) $order->total_amount + 0.001) {
                    throw new BusinessRuleException('Total pembayaran melebihi total tagihan pesanan.', 422, 'INVALID_PAYMENT');
                }

                $isCash = ($method === 'CASH');
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'member_id' => $order->member_id,
                    'amount' => $amount,
                    'method' => $method,
                    'status' => $isCash ? 'VERIFIED' : 'PENDING_VERIFICATION',
                    'verified_by' => $isCash ? $actorUserId : null,
                    'verified_at' => $isCash ? CarbonImmutable::now('UTC') : null,
                    'external_reference' => $externalRef ?: null,
                    'idempotency_key' => $idempotencyKey,
                ]);

                if ($isCash) {
                    $this->syncOrderReadyForPickupIfPaid($order);
                }

                $this->audit->record(
                    'ORDER_PAYMENT_RECORDED',
                    'Payment',
                    (string) $payment->id,
                    null,
                    [
                        'order_id' => $order->id,
                        'method' => $method,
                        'amount' => $amount,
                        'status' => $payment->status,
                        'external_reference' => $payment->external_reference,
                    ],
                    $actorUserId
                );

                return [
                    'status' => 201,
                    'target_resource_id' => (string) $order->id,
                    'body' => [
                        'payment_id' => (string) $payment->id,
                        'order_id' => (string) $order->id,
                        'order_status' => $order->fresh()->status,
                        'status' => $payment->status,
                        'method' => $payment->method,
                        'amount' => (float) $payment->amount,
                        'external_reference' => $payment->external_reference,
                    ],
                ];
            }
        );

        return $execution['body'];
    }

    public function verifyPayment(
        string $paymentId,
        string $action,
        string $actorUserId,
        ?string $actorMemberId = null,
        ?string $idempotencyKey = null
    ): array {
        $cleanKey = $this->idempotency->normalizeKey($idempotencyKey);
        $normalizedAction = strtoupper(trim($action));
        $targetResourceId = strtolower(trim($paymentId));

        $canonicalPayload = [
            'payment_id' => $targetResourceId,
            'action' => $normalizedAction,
        ];

        $execution = $this->idempotency->run(
            $cleanKey,
            'VERIFY_PAYMENT',
            $actorUserId,
            $targetResourceId,
            $canonicalPayload,
            function () use ($paymentId, $normalizedAction, $actorUserId, $actorMemberId): array {
                if (!Str::isUuid($paymentId)) {
                    throw new BusinessRuleException('ID pembayaran tidak valid.', 422, 'INVALID_PAYMENT');
                }

                $payment = Payment::where('id', $paymentId)->lockForUpdate()->first();
                if (!$payment) {
                    throw new BusinessRuleException('Data pembayaran tidak ditemukan.', 404, 'PAYMENT_NOT_FOUND');
                }

                if ($payment->member_id && $actorMemberId && $payment->member_id === $actorMemberId) {
                    throw new BusinessRuleException('Petugas dilarang memverifikasi pembayarannya sendiri.', 403, 'SELF_VERIFICATION_FORBIDDEN');
                }

                if (!in_array($normalizedAction, ['VERIFY', 'REJECT'], true)) {
                    throw new BusinessRuleException('Aksi verifikasi harus VERIFY atau REJECT.', 422, 'INVALID_ACTION');
                }

                if ($payment->status === 'VERIFIED') {
                    if ($normalizedAction === 'VERIFY') {
                        return [
                            'status' => 200,
                            'target_resource_id' => (string) $payment->id,
                            'body' => [
                                'payment_id' => (string) $payment->id,
                                'status' => 'VERIFIED',
                                'verified_at' => $payment->verified_at?->toIso8601String(),
                                'idempotent' => true,
                            ],
                        ];
                    }
                    throw new BusinessRuleException('Pembayaran yang sudah terverifikasi tidak dapat ditolak.', 400, 'PAYMENT_ALREADY_VERIFIED');
                }

                if ($payment->status === 'REJECTED') {
                    if ($normalizedAction === 'REJECT') {
                        return [
                            'status' => 200,
                            'target_resource_id' => (string) $payment->id,
                            'body' => [
                                'payment_id' => (string) $payment->id,
                                'status' => 'REJECTED',
                                'idempotent' => true,
                            ],
                        ];
                    }
                    throw new BusinessRuleException('Pembayaran sudah berstatus REJECTED.', 400, 'PAYMENT_ALREADY_REJECTED');
                }

                $oldStatus = $payment->status;

                if ($normalizedAction === 'REJECT') {
                    $payment->update([
                        'status' => 'REJECTED',
                        'verified_by' => $actorUserId,
                        'verified_at' => null,
                    ]);

                    $this->audit->record(
                        'PAYMENT_REJECTED',
                        'Payment',
                        (string) $payment->id,
                        ['status' => $oldStatus],
                        ['status' => 'REJECTED'],
                        $actorUserId
                    );

                    return [
                        'status' => 200,
                        'target_resource_id' => (string) $payment->id,
                        'body' => [
                            'payment_id' => (string) $payment->id,
                            'status' => 'REJECTED',
                        ],
                    ];
                }

                // VERIFY: terapkan pengurangan saldo piutang atau kesiapan pickup secara atomik
                $verifiedAtUtc = CarbonImmutable::now('UTC');
                $payment->update([
                    'status' => 'VERIFIED',
                    'verified_by' => $actorUserId,
                    'verified_at' => $verifiedAtUtc,
                ]);

                if ($payment->order_id) {
                    $order = Order::where('id', $payment->order_id)->lockForUpdate()->first();
                    if ($order) {
                        $this->syncOrderReadyForPickupIfPaid($order);
                    }
                }

                $pendingAllocs = $payment->pending_allocations;
                if (!empty($pendingAllocs) && is_array($pendingAllocs)) {
                    $this->applyReceivableAllocations($payment, $pendingAllocs, (string) $payment->member_id);
                }

                $this->audit->record(
                    'PAYMENT_VERIFIED',
                    'Payment',
                    (string) $payment->id,
                    ['status' => $oldStatus],
                    [
                        'status' => 'VERIFIED',
                        'verified_by' => $actorUserId,
                        'verified_at' => $payment->verified_at?->toIso8601String(),
                        'allocations_applied' => !empty($pendingAllocs) ? count($pendingAllocs) : 0,
                    ],
                    $actorUserId
                );

                return [
                    'status' => 200,
                    'target_resource_id' => (string) $payment->id,
                    'body' => [
                        'payment_id' => (string) $payment->id,
                        'status' => 'VERIFIED',
                        'verified_at' => $payment->verified_at?->toIso8601String(),
                        'amount' => (float) $payment->amount,
                    ],
                ];
            }
        );

        return $execution['body'];
    }

    public function confirmPickup(string $orderId, ?string $actorUserId = null, ?string $actorMemberId = null, ?string $idempotencyKey = null): array
    {
        $cleanKey = $this->idempotency->normalizeKey($idempotencyKey);
        $resolvedOrder = $this->findOrderByIdentifier($orderId);
        $targetResourceId = $resolvedOrder ? (string) $resolvedOrder->id : ('unresolved:' . strtolower(trim($orderId)));

        $canonicalPayload = [
            'order_id' => $targetResourceId,
        ];

        $execution = $this->idempotency->run(
            $cleanKey,
            'CONFIRM_PICKUP',
            $actorUserId,
            $targetResourceId,
            $canonicalPayload,
            function () use ($orderId, $actorUserId, $actorMemberId, $cleanKey): array {
                $order = $this->findOrderForUpdate($orderId);
                if (!$order) {
                    throw new BusinessRuleException("Pesanan dengan ID / Kode Pickup '{$orderId}' tidak ditemukan.", 404, 'ORDER_NOT_FOUND');
                }

                if ($cleanKey !== null) {
                    $legacyKeyOrder = Order::where('pickup_idempotency_key', $cleanKey)->first();
                    if ($legacyKeyOrder && $legacyKeyOrder->id !== $order->id) {
                        throw new BusinessRuleException('Idempotency key sudah digunakan untuk pesanan lain.', 409, 'IDEMPOTENCY_CONFLICT');
                    }
                    if ($legacyKeyOrder && $legacyKeyOrder->id === $order->id && $order->status === 'COMPLETED') {
                        $receivable = Receivable::where('order_id', $order->id)->first();
                        return [
                            'status' => 200,
                            'target_resource_id' => (string) $order->id,
                            'body' => [
                                'order_id' => (string) $order->id,
                                'status' => 'COMPLETED',
                                'completed_at' => $order->completed_at,
                                'receivable_id' => $receivable?->id,
                                'idempotent' => true,
                            ],
                        ];
                    }
                }

                if ($order->member_id && $actorMemberId && $order->member_id === $actorMemberId) {
                    throw new BusinessRuleException('Kasir tidak boleh mengonfirmasi serah terima pesanan miliknya sendiri.', 403, 'SELF_PICKUP_FORBIDDEN');
                }

                if ($order->status === 'COMPLETED') {
                    throw new BusinessRuleException('Pesanan sudah diselesaikan sebelumnya.', 400, 'ORDER_ALREADY_COMPLETED');
                }

                if ($order->status === 'CANCELLED') {
                    throw new BusinessRuleException('Pesanan yang sudah dibatalkan tidak dapat diserahkan.', 400, 'ORDER_CANCELLED');
                }

                $orderItems = OrderItem::where('order_id', $order->id)->get();
                $totalOrderAmount = (float) $orderItems->sum(fn ($i) => (float) $i->unit_price_snapshot * (int) $i->quantity);

                $verifiedPaid = (float) Payment::where('order_id', $order->id)
                    ->where('status', 'VERIFIED')
                    ->sum('amount');

                if ($order->payment_choice !== 'CREDIT' && ($verifiedPaid + 0.001) < $totalOrderAmount) {
                    throw new BusinessRuleException('Pembayaran tunai/transfer belum terverifikasi lunas sebelum serah terima.', 422, 'PAYMENT_NOT_VERIFIED');
                }

                $reservations = StockReservation::where('order_id', $order->id)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get();
                $stockMovementIds = [];

                foreach ($reservations as $res) {
                    // Urutan lock kanonik: products -> inventory_balances (mencegah deadlock dg purchase_receipts)
                    $product = Product::where('id', $res->product_id)->lockForUpdate()->first();
                    $inventory = InventoryBalance::where('product_id', $res->product_id)->lockForUpdate()->first();
                    if (!$inventory || $inventory->on_hand < $res->quantity || $inventory->reserved < $res->quantity) {
                        throw new BusinessRuleException('Inkonsistensi stok reservasi saat konfirmasi pickup.', 422, 'INSUFFICIENT_STOCK');
                    }

                    $inventory->decrement('on_hand', $res->quantity);
                    $inventory->decrement('reserved', $res->quantity);

                    $movement = StockMovement::create([
                        'product_id' => $res->product_id,
                        'quantity_change' => -$res->quantity,
                        'type' => 'SALE_PICKUP',
                        'reference_id' => $order->id,
                        'reason' => 'Serah terima pesanan selesai',
                    ]);
                    $stockMovementIds[] = (string) $movement->id;

                    // Snapshot HPP final saat barang fisik diserahkan (Keputusan #3), sebelum status menjadi COMPLETED
                    if ($product) {
                        OrderItem::where('order_id', $order->id)
                            ->where('product_id', $res->product_id)
                            ->update(['hpp_snapshot' => (float) $product->current_cost]);
                    }
                }

                StockReservation::where('order_id', $order->id)->delete();

                $creditHold = CreditHold::where('order_id', $order->id)->lockForUpdate()->first();
                if ($creditHold && $creditHold->status === 'ACTIVE') {
                    $creditHold->update(['status' => 'COMMITTED']);
                }

                $receivable = null;
                $creditRemaining = max(0.0, $totalOrderAmount - $verifiedPaid);
                if ($order->payment_choice === 'CREDIT' && $creditRemaining > 0) {
                    $dueDate = CarbonImmutable::now('Asia/Jakarta')->addDays(7)->toDateString();

                    $receivable = Receivable::create([
                        'order_id' => $order->id,
                        'member_id' => $order->member_id,
                        'original_amount' => $creditRemaining,
                        'outstanding_amount' => $creditRemaining,
                        'due_date' => $dueDate,
                        'status' => 'OPEN',
                    ]);
                }

                $completedAtUtc = CarbonImmutable::now('UTC');
                $order->update([
                    'status' => 'COMPLETED',
                    'completed_at' => $completedAtUtc,
                    'pickup_idempotency_key' => $cleanKey,
                ]);

                $this->audit->record(
                    'PICKUP_COMPLETED',
                    'Order',
                    (string) $order->id,
                    ['status' => $order->getOriginal('status')],
                    [
                        'status' => 'COMPLETED',
                        'completed_at' => $completedAtUtc->toIso8601String(),
                        'receivable_id' => $receivable?->id,
                        'stock_movements' => $stockMovementIds,
                    ],
                    $actorUserId
                );

                return [
                    'status' => 200,
                    'target_resource_id' => (string) $order->id,
                    'body' => [
                        'order_id' => (string) $order->id,
                        'status' => 'COMPLETED',
                        'completed_at' => $completedAtUtc->toIso8601String(),
                        'stock_movement_ids' => $stockMovementIds,
                        'receivable_id' => $receivable ? (string) $receivable->id : null,
                        'original_amount' => $receivable ? (float) $receivable->original_amount : null,
                        'outstanding_amount' => $receivable ? (float) $receivable->outstanding_amount : null,
                        'due_date' => $receivable?->due_date?->toDateString(),
                    ],
                ];
            }
        );

        return $execution['body'];
    }

    public function posSale(array $data, ?string $actorUserId = null, ?string $actorMemberId = null): array
    {
        $memberId = $data['member_id'] ?? null;
        $paymentChoice = strtoupper((string) $data['payment_choice']);
        $items = $data['items'];
        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);
        $externalRef = isset($data['external_reference']) ? trim((string) $data['external_reference']) : null;

        $canonicalPayload = [
            'member_id' => $memberId ? strtolower((string) $memberId) : null,
            'payment_choice' => $paymentChoice,
            'external_reference' => $externalRef ?: null,
            'items' => $this->canonicalizeItems($items),
        ];

        $execution = $this->idempotency->run(
            $idempotencyKey,
            'POS_SALE',
            $actorUserId,
            null,
            $canonicalPayload,
            function () use ($memberId, $paymentChoice, $items, $idempotencyKey, $externalRef, $actorUserId, $actorMemberId): array {
                if ($idempotencyKey !== null && Order::where('idempotency_key', $idempotencyKey)->exists()) {
                    throw new BusinessRuleException('Idempotency key sudah digunakan oleh transaksi lain.', 409, 'IDEMPOTENCY_CONFLICT');
                }

                if (!$memberId && $paymentChoice === 'CREDIT') {
                    throw new BusinessRuleException('Penjualan POS untuk non-anggota wajib lunas (Tunai/Transfer).', 422, 'CREDIT_NOT_ELIGIBLE');
                }

                if ($memberId) {
                    $member = Member::where('id', $memberId)->first();
                    if (!$member || strtoupper((string) $member->status) !== 'ACTIVE') {
                        throw new BusinessRuleException('Keanggotaan tidak ditemukan atau tidak aktif.', 422, 'MEMBER_INACTIVE');
                    }
                    if ($paymentChoice === 'CREDIT' && $actorMemberId && $memberId === $actorMemberId) {
                        throw new BusinessRuleException('Kasir tidak diperbolehkan memproses piutang POS atas akunnya sendiri.', 403, 'SELF_PAYMENT_FORBIDDEN');
                    }
                }

                if ($externalRef !== null && $externalRef !== '') {
                    if (Payment::where('external_reference', $externalRef)->exists()) {
                        throw new BusinessRuleException('Referensi transfer sudah pernah tercatat (Duplikasi).', 422, 'DUPLICATE_PAYMENT');
                    }
                }

                $totalAmount = 0.0;
                $orderItemsData = [];
                $inventoryUpdates = [];

                $sortedItems = collect($items)->sortBy(fn ($i) => (string) $i['product_id'])->values()->all();

                foreach ($sortedItems as $item) {
                    // Urutan lock kanonik: products -> inventory_balances agar pembacaan current_cost aman dari balapan WAC
                    $product = $this->findProduct((string) $item['product_id'], true);
                    if (!$product) {
                        throw new BusinessRuleException("Produk tidak ditemukan di master katalog: {$item['product_id']}", 404, 'PRODUCT_NOT_FOUND');
                    }
                    if (isset($product->is_active) && !$product->is_active) {
                        throw new BusinessRuleException("Produk sedang nonaktif: {$product->name}", 422, 'PRODUCT_INACTIVE');
                    }

                    $qty = (int) $item['quantity'];
                    if ($qty <= 0) {
                        throw new BusinessRuleException('Kuantitas barang harus lebih dari 0.', 422, 'INVALID_QUANTITY');
                    }

                    $inventory = InventoryBalance::where('product_id', $product->id)->lockForUpdate()->first();
                    if (!$inventory) {
                        throw new BusinessRuleException("Stok belum tersedia untuk produk: {$product->name}", 422, 'INSUFFICIENT_STOCK');
                    }

                    // Wajib gunakan available = on_hand - reserved agar tidak mencuri stok reservasi online
                    $available = (int) $inventory->on_hand - (int) $inventory->reserved;
                    if ($available < $qty) {
                        throw new BusinessRuleException("Stok tersedia tidak mencukupi untuk produk: {$product->name}", 422, 'INSUFFICIENT_STOCK');
                    }

                    $subtotal = (float) $product->selling_price * $qty;
                    $totalAmount += $subtotal;

                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price_snapshot' => (float) $product->selling_price,
                        'hpp_snapshot' => (float) $product->current_cost,
                    ];

                    $inventoryUpdates[] = [
                        'inventory' => $inventory,
                        'quantity' => $qty,
                    ];
                }

                if ($paymentChoice === 'CREDIT') {
                    $this->validateAndLockMemberCredit((string) $memberId, $totalAmount);
                }

                $completedAtUtc = CarbonImmutable::now('UTC');
                $orderNo = 'POS-' . CarbonImmutable::now('Asia/Jakarta')->format('Ymd') . '-' . strtoupper(Str::random(5));

                $order = Order::create([
                    'member_id' => $memberId,
                    'channel' => 'POS',
                    'status' => 'COMPLETED',
                    'payment_choice' => $paymentChoice,
                    'order_no' => $orderNo,
                    'total_amount' => $totalAmount,
                    'completed_at' => $completedAtUtc,
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($inventoryUpdates as $update) {
                    $inv = $update['inventory'];
                    $qty = $update['quantity'];

                    $inv->decrement('on_hand', $qty);

                    StockMovement::create([
                        'product_id' => $inv->product_id,
                        'quantity_change' => -$qty,
                        'type' => 'POS_SALE',
                        'reference_id' => $order->id,
                        'reason' => 'Penjualan langsung POS',
                    ]);
                }

                foreach ($orderItemsData as $orderItem) {
                    OrderItem::create(array_merge($orderItem, ['order_id' => $order->id]));
                }

                $receivableId = null;
                if ($paymentChoice === 'CREDIT') {
                    $dueDate = CarbonImmutable::now('Asia/Jakarta')->addDays(7)->toDateString();

                    $receivable = Receivable::create([
                        'order_id' => $order->id,
                        'member_id' => $memberId,
                        'original_amount' => $totalAmount,
                        'outstanding_amount' => $totalAmount,
                        'due_date' => $dueDate,
                        'status' => 'OPEN',
                    ]);
                    $receivableId = (string) $receivable->id;
                } else {
                    Payment::create([
                        'order_id' => $order->id,
                        'member_id' => $memberId,
                        'amount' => $totalAmount,
                        'method' => $paymentChoice,
                        'status' => 'VERIFIED',
                        'verified_by' => $actorUserId,
                        'verified_at' => $completedAtUtc,
                        'external_reference' => $externalRef ?: null,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                }

                $this->audit->record(
                    'POS_SALE_COMPLETED',
                    'Order',
                    (string) $order->id,
                    null,
                    [
                        'order_no' => $orderNo,
                        'member_id' => $memberId,
                        'payment_choice' => $paymentChoice,
                        'total_amount' => $totalAmount,
                        'receivable_id' => $receivableId,
                    ],
                    $actorUserId
                );

                return [
                    'status' => 201,
                    'target_resource_id' => (string) $order->id,
                    'body' => [
                        'order_id' => (string) $order->id,
                        'order_no' => $order->order_no,
                        'status' => 'COMPLETED',
                        'channel' => 'POS',
                        'total_amount' => (float) $totalAmount,
                        'completed_at' => $completedAtUtc->toIso8601String(),
                        'receivable_id' => $receivableId,
                    ],
                ];
            }
        );

        return $execution['body'];
    }

    public function payReceivable(array $data, ?string $actorUserId = null, ?string $actorMemberId = null): array
    {
        $memberId = (string) $data['member_id'];
        $method = strtoupper((string) $data['method']);
        $amount = (float) $data['amount'];
        $allocations = $data['allocations'];
        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);
        $externalRef = isset($data['external_reference']) ? trim((string) $data['external_reference']) : null;

        $canonicalPayload = [
            'member_id' => strtolower($memberId),
            'method' => $method,
            'amount' => round($amount, 2),
            'external_reference' => $externalRef ?: null,
            'allocations' => $this->canonicalizeAllocations($allocations),
        ];

        $execution = $this->idempotency->run(
            $idempotencyKey,
            'PAY_RECEIVABLE',
            $actorUserId,
            strtolower($memberId),
            $canonicalPayload,
            function () use ($memberId, $method, $amount, $allocations, $idempotencyKey, $externalRef, $actorUserId, $actorMemberId): array {
                if ($idempotencyKey !== null && Payment::where('idempotency_key', $idempotencyKey)->exists()) {
                    throw new BusinessRuleException('Idempotency key sudah digunakan oleh pembayaran lain.', 409, 'IDEMPOTENCY_CONFLICT');
                }

                if ($actorMemberId && $memberId === $actorMemberId) {
                    throw new BusinessRuleException('Petugas tidak diperbolehkan memproses pembayaran piutangnya sendiri.', 403, 'SELF_PAYMENT_FORBIDDEN');
                }

                if ($amount <= 0) {
                    throw new BusinessRuleException('Nominal pembayaran harus lebih besar dari nol.', 422, 'INVALID_PAYMENT');
                }

                $totalAllocated = round((float) collect($allocations)->sum('amount'), 2);
                if (abs($totalAllocated - round($amount, 2)) > 0.01) {
                    throw new BusinessRuleException('Total alokasi pembayaran tidak sesuai dengan nominal pembayaran.', 422, 'INVALID_PAYMENT');
                }

                if ($externalRef !== null && $externalRef !== '') {
                    if (Payment::where('external_reference', $externalRef)->exists()) {
                        throw new BusinessRuleException('Referensi transfer sudah pernah digunakan (Duplikasi).', 422, 'DUPLICATE_PAYMENT');
                    }
                }

                // Validasi kepemilikan & sisa saldo seluruh alokasi sebelum menyimpan pembayaran
                $this->validateAllocationsBeforeRecording($allocations, $memberId);

                $isCash = ($method === 'CASH');
                $payment = Payment::create([
                    'member_id' => $memberId,
                    'amount' => $amount,
                    'method' => $method,
                    'status' => $isCash ? 'VERIFIED' : 'PENDING_VERIFICATION',
                    'verified_by' => $isCash ? $actorUserId : null,
                    'verified_at' => $isCash ? CarbonImmutable::now('UTC') : null,
                    'external_reference' => $externalRef ?: null,
                    'idempotency_key' => $idempotencyKey,
                    'pending_allocations' => $isCash ? null : $allocations,
                ]);

                if ($isCash) {
                    $this->applyReceivableAllocations($payment, $allocations, $memberId);
                }

                $this->audit->record(
                    'RECEIVABLE_PAYMENT_CREATED',
                    'Payment',
                    (string) $payment->id,
                    null,
                    [
                        'member_id' => $memberId,
                        'method' => $method,
                        'amount' => $amount,
                        'status' => $payment->status,
                        'allocations' => $allocations,
                    ],
                    $actorUserId
                );

                return [
                    'status' => 200,
                    'target_resource_id' => strtolower($memberId),
                    'body' => [
                        'payment_id' => (string) $payment->id,
                        'status' => $payment->status,
                        'amount' => (float) $amount,
                        'message' => $isCash
                            ? 'Pembayaran cicilan berhasil dicatat dan piutang telah dipotong.'
                            : 'Pembayaran transfer berhasil dicatat dan menunggu verifikasi petugas sebelum memotong piutang.',
                    ],
                ];
            }
        );

        return $execution['body'];
    }

    public function cancelOrder(string $orderId, ?string $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $actorUserId) {
            $order = $this->findOrderForUpdate($orderId);

            if (!$order) {
                throw new BusinessRuleException("Pesanan dengan ID / Kode '{$orderId}' tidak ditemukan.", 404, 'ORDER_NOT_FOUND');
            }

            if ($order->status === 'COMPLETED') {
                throw new BusinessRuleException('Pesanan yang sudah selesai (COMPLETED) tidak dapat dibatalkan.', 400, 'ORDER_ALREADY_COMPLETED');
            }

            if ($order->status === 'CANCELLED') {
                return [
                    'order_id' => $order->id,
                    'status' => 'CANCELLED',
                    'idempotent' => true,
                    'message' => 'Pesanan sudah dibatalkan sebelumnya (Idempotent).',
                ];
            }

            $reservations = StockReservation::where('order_id', $order->id)->get();

            foreach ($reservations as $res) {
                $inventory = InventoryBalance::where('product_id', $res->product_id)->lockForUpdate()->first();
                if ($inventory) {
                    $inventory->decrement('reserved', $res->quantity);
                }
            }

            StockReservation::where('order_id', $order->id)->delete();

            $creditHold = CreditHold::where('order_id', $order->id)->lockForUpdate()->first();
            if ($creditHold && $creditHold->status === 'ACTIVE') {
                $creditHold->update(['status' => 'RELEASED']);
            }

            $oldStatus = $order->status;
            $order->update(['status' => 'CANCELLED']);

            $this->audit->record(
                'ORDER_CANCELLED',
                'Order',
                (string) $order->id,
                ['status' => $oldStatus],
                ['status' => 'CANCELLED'],
                $actorUserId
            );

            return [
                'order_id' => $order->id,
                'status' => 'CANCELLED',
                'message' => 'Pesanan berhasil dibatalkan, reservasi stok dan hold kredit telah dilepas.',
            ];
        });
    }

    public function getMemberCreditSummary(string $memberId): array
    {
        $policy = CreditPolicy::where('active', true)->latest('effective_at')->first();
        $globalLimit = $policy ? (float) $policy->limit_amount : 0.0;
        $dueDays = $policy ? (int) $policy->due_days : 7;

        $creditAccount = MemberCreditAccount::where('member_id', $memberId)->first();
        $outstanding = (float) Receivable::where('member_id', $memberId)
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        $activeHolds = (float) CreditHold::where('member_id', $memberId)
            ->where('status', 'ACTIVE')
            ->sum('amount');

        $todayJakarta = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $hasOverdue = Receivable::where('member_id', $memberId)
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', $todayJakarta)
            ->exists();

        $availableLimit = max(0.0, $globalLimit - $outstanding - $activeHolds);

        return [
            'member_id' => $memberId,
            'credit_account_status' => $creditAccount?->status ?? 'BLOCKED',
            'global_limit' => $globalLimit,
            'due_days' => $dueDays,
            'outstanding_receivable' => $outstanding,
            'active_credit_hold' => $activeHolds,
            'available_limit' => $availableLimit,
            'has_overdue' => $hasOverdue,
            'eligible_for_credit' => ($creditAccount && $creditAccount->status === 'ACTIVE' && !$hasOverdue && $availableLimit > 0),
        ];
    }

    private function validateAndLockMemberCredit(string $memberId, float $totalAmount): void
    {
        $creditAccount = MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
        if (!$creditAccount || strtoupper((string) $creditAccount->status) !== 'ACTIVE') {
            throw new BusinessRuleException('Fasilitas kredit anggota tidak aktif atau diblokir.', 422, 'CREDIT_NOT_ELIGIBLE');
        }

        $todayJakarta = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $hasOverdue = Receivable::where('member_id', $memberId)
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', $todayJakarta)
            ->exists();

        if ($hasOverdue) {
            throw new BusinessRuleException('Anggota memiliki tunggakan piutang lewat jatuh tempo. Kredit baru ditolak.', 422, 'OVERDUE_RECEIVABLE');
        }

        $policy = CreditPolicy::where('active', true)->latest('effective_at')->first();
        if (!$policy) {
            throw new BusinessRuleException('Kebijakan limit global belum dikonfigurasi oleh Manajer.', 422, 'CREDIT_POLICY_UNSET');
        }

        $outstanding = (float) Receivable::where('member_id', $memberId)
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        $activeHolds = (float) CreditHold::where('member_id', $memberId)
            ->where('status', 'ACTIVE')
            ->sum('amount');

        if (($outstanding + $activeHolds + $totalAmount) > (float) $policy->limit_amount + 0.001) {
            throw new BusinessRuleException('Limit piutang global tidak mencukupi untuk transaksi ini.', 422, 'CREDIT_LIMIT_EXCEEDED');
        }
    }

    private function syncOrderReadyForPickupIfPaid(Order $order): void
    {
        if ($order->payment_choice === 'CREDIT' || $order->status !== 'PENDING_PAYMENT') {
            return;
        }

        $verifiedSum = (float) Payment::where('order_id', $order->id)
            ->where('status', 'VERIFIED')
            ->sum('amount');

        if (($verifiedSum + 0.001) >= (float) $order->total_amount) {
            $order->update(['status' => 'READY_FOR_PICKUP']);
        }
    }

    private function validateAllocationsBeforeRecording(array $allocations, string $memberId): void
    {
        foreach ($allocations as $alloc) {
            $allocAmount = (float) ($alloc['amount'] ?? 0);
            if ($allocAmount <= 0) {
                throw new BusinessRuleException('Nominal alokasi wajib lebih besar dari nol.', 422, 'INVALID_PAYMENT');
            }

            $recId = (string) ($alloc['receivable_id'] ?? '');
            if (!Str::isUuid($recId)) {
                throw new BusinessRuleException('ID tagihan piutang tidak valid.', 422, 'INVALID_PAYMENT');
            }

            $receivable = Receivable::where('id', $recId)->lockForUpdate()->first();
            if (!$receivable) {
                throw new BusinessRuleException('Tagihan piutang tidak ditemukan.', 404, 'RECEIVABLE_NOT_FOUND');
            }
            if ((string) $receivable->member_id !== $memberId) {
                throw new BusinessRuleException('Tagihan piutang tidak sesuai dengan anggota yang membayar.', 422, 'FORBIDDEN');
            }
            if ($allocAmount > (float) $receivable->outstanding_amount + 0.001) {
                throw new BusinessRuleException('Nominal alokasi melebihi sisa saldo tagihan piutang.', 422, 'INVALID_PAYMENT');
            }
        }
    }

    private function applyReceivableAllocations(Payment $payment, array $allocations, string $memberId): void
    {
        foreach ($allocations as $alloc) {
            $allocAmount = (float) $alloc['amount'];
            $receivable = Receivable::where('id', (string) $alloc['receivable_id'])->lockForUpdate()->first();

            if (!$receivable) {
                throw new BusinessRuleException('Tagihan piutang tidak ditemukan saat alokasi.', 404, 'RECEIVABLE_NOT_FOUND');
            }
            if ((string) $receivable->member_id !== $memberId) {
                throw new BusinessRuleException('Tagihan piutang tidak sesuai dengan anggota yang membayar.', 422, 'FORBIDDEN');
            }
            if ($allocAmount > (float) $receivable->outstanding_amount + 0.001) {
                throw new BusinessRuleException('Nominal alokasi melebihi sisa saldo tagihan saat verifikasi.', 422, 'INVALID_PAYMENT');
            }

            ReceivablePaymentAllocation::create([
                'payment_id' => $payment->id,
                'receivable_id' => $receivable->id,
                'allocated_amount' => $allocAmount,
            ]);

            $newOutstanding = max(0.0, round((float) $receivable->outstanding_amount - $allocAmount, 2));
            $newStatus = ($newOutstanding <= 0) ? 'PAID' : 'PARTIALLY_PAID';

            // Jatuh tempo awal (due_date) tidak boleh diubah oleh pembayaran sebagian
            $receivable->update([
                'outstanding_amount' => $newOutstanding,
                'status' => $newStatus,
            ]);
        }
    }

    private function findOrderByIdentifier(string $identifier, bool $lockForUpdate = false): ?Order
    {
        $cleanOrderId = trim($identifier);
        $isUuid = Str::isUuid($cleanOrderId);

        $query = $lockForUpdate ? Order::lockForUpdate() : Order::query();
        if ($isUuid) {
            $query->where('id', $cleanOrderId)
                ->orWhere('pickup_code', $cleanOrderId)
                ->orWhere('order_no', $cleanOrderId);
        } else {
            $query->where('pickup_code', $cleanOrderId)
                ->orWhere('order_no', $cleanOrderId);
        }

        return $query->first();
    }

    private function findOrderForUpdate(string $identifier): ?Order
    {
        return $this->findOrderByIdentifier($identifier, true);
    }

    private function canonicalizeItems(array $items): array
    {
        return collect($items)
            ->map(fn ($item) => [
                'product_id' => strtolower(trim((string) ($item['product_id'] ?? ''))),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ])
            ->sortBy('product_id')
            ->values()
            ->all();
    }

    private function canonicalizeAllocations(array $allocations): array
    {
        return collect($allocations)
            ->map(fn ($item) => [
                'receivable_id' => strtolower(trim((string) ($item['receivable_id'] ?? ''))),
                'amount' => round((float) ($item['amount'] ?? 0), 2),
            ])
            ->sortBy('receivable_id')
            ->values()
            ->all();
    }

    private function generateUniquePickupCode(): string
    {
        do {
            $code = 'PKP-' . random_int(10000, 99999);
        } while (Order::where('pickup_code', $code)->exists());

        return $code;
    }

    public function findProduct(string $identifier, bool $lockForUpdate = false)
    {
        $cleanId = trim($identifier);
        $isUuid = Str::isUuid($cleanId);

        $query = DB::table('products');

        $query->where(function ($q) use ($cleanId, $isUuid) {
            if ($isUuid) {
                $q->where('id', $cleanId)
                    ->orWhere('sku', $cleanId)
                    ->orWhere('barcode', $cleanId);
            } else {
                $q->where('sku', $cleanId)
                    ->orWhere('barcode', $cleanId);
            }

            if (Schema::hasColumn('products', 'prd_id')) {
                $q->orWhere('prd_id', $cleanId);
            }
        });

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $result = $query->first();

        if (!$result && str_starts_with(strtoupper($cleanId), 'PRD-')) {
            $prdMap = [
                'PRD-001' => 'SEM-001',
                'PRD-002' => 'MNY-001',
                'PRD-003' => 'GUL-001',
                'PRD-004' => 'SSU-001',
                'PRD-005' => 'TLR-001',
                'PRD-006' => 'TPG-001',
                'PRD-007' => 'KCP-001',
                'PRD-008' => 'SRD-001',
                'PRD-009' => 'GRM-001',
                'PRD-010' => 'MRG-001',
                'PRD-011' => 'SBN-001',
                'PRD-012' => 'PRG-001',
                'PRD-013' => 'LNT-001',
                'PRD-014' => 'TIS-001',
                'PRD-015' => 'SBN-002',
                'PRD-016' => 'KPI-001',
                'PRD-017' => 'TEH-001',
                'PRD-018' => 'BSK-001',
                'PRD-019' => 'AIR-001',
                'PRD-020' => 'KRP-001',
            ];
            $sku = $prdMap[strtoupper($cleanId)] ?? null;
            if ($sku) {
                $fallbackQuery = DB::table('products')->where('sku', $sku);
                if ($lockForUpdate) {
                    $fallbackQuery->lockForUpdate();
                }
                $result = $fallbackQuery->first();
            }
        }

        return $result;
    }
}
