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
        $expectedTotal = $data['expected_total'] ?? null;
        if ($channel !== 'ONLINE_PICKUP' || !in_array($paymentChoice, ['CASH', 'BANK_TRANSFER', 'CREDIT'], true)) {
            throw new BusinessRuleException('Checkout hanya mendukung pickup dan metode pembayaran yang sah.', 422, 'INVALID_ORDER');
        }
        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);

        $canonicalPayload = [
            'channel' => strtoupper(trim($channel)),
            'member_id' => $memberId ? strtolower((string) $memberId) : null,
            'payment_choice' => $paymentChoice,
            'items' => $this->canonicalizeItems($items),
        ];

        if ($expectedTotal !== null) {
            $canonicalPayload['expected_total'] = Money::decimal(Money::minor($expectedTotal));
        }
        $execution = $this->idempotency->run(
            $idempotencyKey,
            'CHECKOUT',
            $actorUserId,
            null,
            $canonicalPayload,
            function () use ($memberId, $paymentChoice, $items, $channel, $actorUserId, $idempotencyKey, $expectedTotal): array {
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

                if ($paymentChoice === 'CREDIT') {
                    MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
                }
                $totalMinor = 0;
                $orderItemsData = [];

                // Urutkan product_id agar menghindari deadlock saat locking banyak item secara paralel
                $sortedItems = $this->resolveProductQuantities($items);

                foreach ($sortedItems as $item) {
                    // Urutan lock kanonik: products -> inventory_balances agar pembacaan current_cost aman dari balapan WAC
                    $product = DB::table('products')->where('id', $item['product_id'])->lockForUpdate()->first();
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

                    $totalMinor = Money::addLine($totalMinor, $product->selling_price, $qty);

                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        // Harga jual di-snapshot saat checkout; HPP disnapshot final saat serah terima (COMPLETED)
                        'unit_price_snapshot' => $product->selling_price,
                        'hpp_snapshot' => null,
                    ];
                }

                $totalAmount = Money::amount(Money::decimal($totalMinor));
                if ($expectedTotal !== null && Money::minor($expectedTotal) !== $totalMinor) {
                    throw new BusinessRuleException('Harga produk berubah. Muat ulang katalog dan periksa total pesanan.', 409, 'PRICE_CHANGED');
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
                    app(InventoryService::class)->reserve($order->id, $orderItem['product_id'], $orderItem['quantity']);
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
        if (!in_array($method, ['CASH', 'BANK_TRANSFER'], true)) {
            throw new BusinessRuleException('Metode pembayaran tidak valid.', 422, 'INVALID_PAYMENT_METHOD');
        }
        $amount = Money::amount($data['amount']);
        $externalRef = isset($data['external_reference']) ? trim((string) $data['external_reference']) : null;

        $resolvedOrder = $this->findOrderByIdentifier($orderIdentifier);
        $targetResourceId = $resolvedOrder ? (string) $resolvedOrder->id : ('unresolved:' . strtolower(trim($orderIdentifier)));

        $canonicalPayload = [
            'order_id' => $targetResourceId,
            'method' => $method,
            'amount' => (float) $amount,
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

                $alreadyAccounted = Payment::where('order_id', $order->id)
                    ->whereIn('status', ['VERIFIED', 'PENDING_VERIFICATION'])
                    ->sum('amount');

                if (Money::minor($alreadyAccounted) + Money::minor($amount) > Money::minor($order->getRawOriginal('total_amount'))) {
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

                $candidate = Payment::find($paymentId);
                if ($candidate?->order_id) {
                    Order::where('id', $candidate->order_id)->lockForUpdate()->first();
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

                if ($payment->status !== 'PENDING_VERIFICATION' || $payment->method !== 'BANK_TRANSFER') {
                    throw new BusinessRuleException('Status pembayaran tidak dapat diverifikasi.', 422, 'INVALID_PAYMENT');
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

                // Reject verification for a cancelled/completed order, including legacy pending payments.
                $order = null;
                if ($payment->order_id) {
                    $order = Order::where('id', $payment->order_id)->lockForUpdate()->first();
                    if (!$order || in_array($order->status, ['CANCELLED', 'COMPLETED'], true)) {
                        throw new BusinessRuleException('Pembayaran tidak dapat diverifikasi untuk pesanan ini.', 409, 'INVALID_ORDER_STATUS');
                    }
                }

                // VERIFY: terapkan pengurangan saldo piutang atau kesiapan pickup secara atomik
                $verifiedAtUtc = CarbonImmutable::now('UTC');
                $payment->update([
                    'status' => 'VERIFIED',
                    'verified_by' => $actorUserId,
                    'verified_at' => $verifiedAtUtc,
                ]);

                if ($order) {
                    $this->syncOrderReadyForPickupIfPaid($order);
                }

                $pendingAllocs = $payment->pending_allocations;
                if (!$order) {
                    if (empty($pendingAllocs) || !is_array($pendingAllocs) || !$payment->member_id) {
                        throw new BusinessRuleException('Pembayaran piutang tidak memiliki alokasi valid.', 422, 'INVALID_PAYMENT');
                    }
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

    public function confirmPickup(string $orderId, ?string $actorUserId = null, ?string $actorMemberId = null, ?string $idempotencyKey = null, ?string $pickupCode = null, bool $handoverConfirmed = false): array
    {
        $cleanKey = $this->idempotency->normalizeKey($idempotencyKey);
        $resolvedOrder = $this->findOrderByIdentifier($orderId);
        $targetResourceId = $resolvedOrder ? (string) $resolvedOrder->id : ('unresolved:' . strtolower(trim($orderId)));
        if (!$resolvedOrder) {
            throw new BusinessRuleException('Pesanan tidak ditemukan.', 404, 'ORDER_NOT_FOUND');
        }
        if (!$handoverConfirmed || !$pickupCode || !$resolvedOrder->pickup_code
            || !hash_equals((string) $resolvedOrder->pickup_code, $pickupCode)) {
            throw new BusinessRuleException('Kode pickup atau konfirmasi serah terima tidak valid.', 422, 'INVALID_PICKUP_CODE');
        }


        $canonicalPayload = [
            'order_id' => $targetResourceId,
        ];

        $execution = $this->idempotency->run(
            $cleanKey,
            'CONFIRM_PICKUP',
            $actorUserId,
            $targetResourceId,
            $canonicalPayload,
            function () use ($orderId, $actorUserId, $actorMemberId, $cleanKey, $pickupCode, $handoverConfirmed): array {
                $order = $this->findOrderForUpdate($orderId);
                if (!$order) {
                    throw new BusinessRuleException("Pesanan dengan ID / Kode Pickup '{$orderId}' tidak ditemukan.", 404, 'ORDER_NOT_FOUND');
                }

                if (!$handoverConfirmed || !$pickupCode || !$order->pickup_code
                    || !hash_equals((string) $order->pickup_code, $pickupCode)) {
                    throw new BusinessRuleException('Kode pickup atau konfirmasi serah terima tidak valid.', 422, 'INVALID_PICKUP_CODE');
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
                $totalOrderMinor = 0;
                foreach ($orderItems as $item) {
                    $totalOrderMinor = Money::addLine($totalOrderMinor, $item->getRawOriginal('unit_price_snapshot'), (int) $item->quantity);
                }
                $totalOrderAmount = Money::decimal($totalOrderMinor);
                if ($totalOrderMinor !== Money::minor($order->getRawOriginal('total_amount'))) {
                    throw new BusinessRuleException('Total item tidak cocok dengan total pesanan.', 422, 'INVALID_ORDER_TOTAL');
                }

                $verifiedPaid = Payment::where('order_id', $order->id)
                    ->where('status', 'VERIFIED')
                    ->sum('amount');

                if ($order->payment_choice !== 'CREDIT' && Money::minor($verifiedPaid) < Money::minor($totalOrderAmount)) {
                    throw new BusinessRuleException('Pembayaran tunai/transfer belum terverifikasi lunas sebelum serah terima.', 422, 'PAYMENT_NOT_VERIFIED');
                }

                if ($order->channel !== 'ONLINE_PICKUP') {
                    throw new BusinessRuleException('Pickup hanya untuk pesanan online.', 422, 'INVALID_ORDER_STATUS');
                }
                $expectedStatus = $order->payment_choice === 'CREDIT' ? 'PENDING_PICKUP' : 'READY_FOR_PICKUP';
                if ($order->status !== $expectedStatus) {
                    throw new BusinessRuleException('Status pesanan belum siap untuk serah terima.', 422, 'INVALID_ORDER_STATUS');
                }

                if (Payment::where('order_id', $order->id)->where('status', 'PENDING_VERIFICATION')->exists()) {
                    throw new BusinessRuleException('Selesaikan verifikasi pembayaran sebelum pickup.', 409, 'PAYMENT_VERIFICATION_REQUIRED');
                }
                if ($order->payment_choice === 'CREDIT') {
                    MemberCreditAccount::where('member_id', $order->member_id)->lockForUpdate()->first();
                }
                $reservations = $this->lockMatchingReservations($order);
                $creditHold = $this->lockRequiredCreditHold($order);
                $stockMovementIds = [];

                foreach ($reservations as $res) {
                    // Urutan lock kanonik: products -> inventory_balances (mencegah deadlock dg purchase_receipts)
                    $product = Product::where('id', $res->product_id)->lockForUpdate()->first();
                    $inventory = InventoryBalance::where('product_id', $res->product_id)->lockForUpdate()->first();
                    if (!$product || !$inventory || $inventory->on_hand < $inventory->reserved
                        || $inventory->reserved < $res->quantity) {
                        throw new BusinessRuleException('Inkonsistensi stok reservasi saat konfirmasi pickup.', 422, 'INSUFFICIENT_STOCK');
                    }

                    $movement = app(InventoryService::class)->fulfill($res, $inventory);
                    $stockMovementIds[] = (string) $movement->id;

                    // Snapshot HPP final saat barang fisik diserahkan (Keputusan #3), sebelum status menjadi COMPLETED
                    if ($product) {
                        OrderItem::where('order_id', $order->id)
                            ->where('product_id', $res->product_id)
                            ->update(['hpp_snapshot' => $product->current_cost]);
                    }
                }

                if ($creditHold) {
                    $creditHold->update(['status' => 'COMMITTED']);
                }

                $receivable = null;
                $creditRemaining = Money::decimal(max(0, Money::minor($totalOrderAmount) - Money::minor($verifiedPaid)));
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

                $oldOrderStatus = $order->status;
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
                    ['status' => $oldOrderStatus],
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
        $expectedTotal = $data['expected_total'] ?? null;
        $cashReceived = $data['cash_received'] ?? null;
        if (!in_array($paymentChoice, ['CASH', 'BANK_TRANSFER', 'CREDIT'], true)) {
            throw new BusinessRuleException('Metode pembayaran tidak valid.', 422, 'INVALID_PAYMENT_METHOD');
        }
        if ($paymentChoice === 'BANK_TRANSFER' && (($data['transfer_verified'] ?? false) !== true || !$actorUserId)) {
            throw new BusinessRuleException('Petugas wajib memastikan dana transfer telah diterima.', 422, 'PAYMENT_NOT_VERIFIED');
        }
        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);
        $externalRef = isset($data['external_reference']) ? trim((string) $data['external_reference']) : null;

        $canonicalPayload = [
            'member_id' => $memberId ? strtolower((string) $memberId) : null,
            'payment_choice' => $paymentChoice,
            'external_reference' => $externalRef ?: null,
            'items' => $this->canonicalizeItems($items),
        ];

        if ($expectedTotal !== null) {
            $canonicalPayload['expected_total'] = Money::decimal(Money::minor($expectedTotal));
        }
        if ($cashReceived !== null && $paymentChoice === 'CASH') {
            $canonicalPayload['cash_received'] = Money::decimal(Money::minor($cashReceived));
        }
        $execution = $this->idempotency->run(
            $idempotencyKey,
            'POS_SALE',
            $actorUserId,
            null,
            $canonicalPayload,
            function () use ($memberId, $paymentChoice, $items, $idempotencyKey, $externalRef, $actorUserId, $actorMemberId, $expectedTotal, $cashReceived): array {
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
                    if ($actorMemberId && $memberId === $actorMemberId) {
                        throw new BusinessRuleException('Kasir tidak diperbolehkan memproses piutang POS atas akunnya sendiri.', 403, 'SELF_PAYMENT_FORBIDDEN');
                    }
                }

                if ($externalRef !== null && $externalRef !== '') {
                    if (Payment::where('external_reference', $externalRef)->exists()) {
                        throw new BusinessRuleException('Referensi transfer sudah pernah tercatat (Duplikasi).', 422, 'DUPLICATE_PAYMENT');
                    }
                }

                if ($paymentChoice === 'CREDIT') {
                    MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
                }
                $totalMinor = 0;
                $orderItemsData = [];
                $inventoryUpdates = [];

                $sortedItems = $this->resolveProductQuantities($items);

                foreach ($sortedItems as $item) {
                    // Urutan lock kanonik: products -> inventory_balances agar pembacaan current_cost aman dari balapan WAC
                    $product = DB::table('products')->where('id', $item['product_id'])->lockForUpdate()->first();
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

                    $totalMinor = Money::addLine($totalMinor, $product->selling_price, $qty);

                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'unit_price_snapshot' => $product->selling_price,
                        'hpp_snapshot' => $product->current_cost,
                    ];

                    $inventoryUpdates[] = [
                        'inventory' => $inventory,
                        'quantity' => $qty,
                    ];
                }

                $totalAmount = Money::amount(Money::decimal($totalMinor));
                if ($paymentChoice === 'CASH' && $cashReceived !== null && Money::minor($cashReceived) < $totalMinor) {
                    throw new BusinessRuleException('Uang tunai diterima kurang dari total transaksi.', 422, 'INSUFFICIENT_CASH');
                }
                if ($expectedTotal !== null && Money::minor($expectedTotal) !== $totalMinor) {
                    throw new BusinessRuleException('Harga berubah. Muat ulang katalog dan konfirmasi ulang nominal pembayaran.', 409, 'PRICE_CHANGED');
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

                    app(InventoryService::class)->sell($inv, $qty, (string) $order->id);
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
        if (!in_array($method, ['CASH', 'BANK_TRANSFER'], true)) {
            throw new BusinessRuleException('Metode pembayaran tidak valid.', 422, 'INVALID_PAYMENT_METHOD');
        }
        $amount = Money::amount($data['amount']);
        $allocations = $data['allocations'] ?? null;
        $idempotencyKey = $this->idempotency->normalizeKey($data['idempotency_key'] ?? null);
        $externalRef = isset($data['external_reference']) ? trim((string) $data['external_reference']) : null;

        $canonicalPayload = [
            'member_id' => strtolower($memberId),
            'method' => $method,
            'amount' => (float) $amount,
            'external_reference' => $externalRef ?: null,
            'allocations' => $allocations === null ? null : $this->canonicalizeAllocations($allocations),
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

                MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
                if (!Member::where('id', $memberId)->exists()) {
                    throw new BusinessRuleException('Anggota tidak ditemukan.', 404, 'MEMBER_NOT_FOUND');
                }
                $allocations ??= $this->allocateOldestReceivables($memberId, Money::minor($amount));
                $totalAllocated = array_sum(array_map(static fn ($a) => Money::minor($a['amount']), $allocations));
                if ($totalAllocated !== Money::minor($amount)) {
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

            // Refund is not implemented yet. Do not cancel an order with received funds.
            if (Payment::where('order_id', $order->id)->where('status', 'VERIFIED')->exists()) {
                throw new BusinessRuleException('Pesanan sudah dibayar; selesaikan prosedur refund terlebih dahulu.', 409, 'REFUND_REQUIRED');
            }
            if (Payment::where('order_id', $order->id)->where('status', 'PENDING_VERIFICATION')->exists()) {
                throw new BusinessRuleException('Selesaikan pemeriksaan pembayaran transfer sebelum membatalkan pesanan.', 409, 'PAYMENT_VERIFICATION_REQUIRED');
            }

            if ($order->channel !== 'ONLINE_PICKUP' || !in_array($order->status, ['PENDING_PAYMENT', 'PENDING_PICKUP', 'READY_FOR_PICKUP'], true)) {
                throw new BusinessRuleException('Status pesanan tidak dapat dibatalkan.', 422, 'INVALID_ORDER_STATUS');
            }
            if ($order->payment_choice === 'CREDIT') {
                MemberCreditAccount::where('member_id', $order->member_id)->lockForUpdate()->first();
            }
            $reservations = $this->lockMatchingReservations($order);
            $creditHold = $this->lockRequiredCreditHold($order);

            foreach ($reservations as $res) {
                $product = Product::where('id', $res->product_id)->lockForUpdate()->first();
                $inventory = InventoryBalance::where('product_id', $res->product_id)->lockForUpdate()->first();
                if (!$product || !$inventory || $inventory->on_hand < $inventory->reserved
                    || $inventory->reserved < $res->quantity) {
                    throw new BusinessRuleException('Inkonsistensi stok reservasi saat pembatalan.', 422, 'INSUFFICIENT_STOCK');
                }
                app(InventoryService::class)->release($res, $inventory);
            }

            if ($creditHold) {
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
        $globalLimit = $policy ? $policy->getRawOriginal('limit_amount') : '0.00';
        $dueDays = $policy ? (int) $policy->due_days : 7;

        $creditAccount = MemberCreditAccount::where('member_id', $memberId)->first();
        $outstanding = Receivable::where('member_id', $memberId)
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        $activeHolds = CreditHold::where('member_id', $memberId)
            ->where('status', 'ACTIVE')
            ->sum('amount');

        $todayJakarta = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $hasOverdue = Receivable::where('member_id', $memberId)
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', $todayJakarta)
            ->exists();

        $availableLimit = (float) Money::decimal(max(0, Money::minor($globalLimit) - Money::minor($outstanding) - Money::minor($activeHolds)));

        return [
            'member_id' => $memberId,
            'credit_account_status' => $creditAccount?->status ?? 'BLOCKED',
            'global_limit' => (float) $globalLimit,
            'due_days' => $dueDays,
            'outstanding_receivable' => (float) $outstanding,
            'active_credit_hold' => (float) $activeHolds,
            'available_limit' => $availableLimit,
            'has_overdue' => $hasOverdue,
            'eligible_for_credit' => (Member::where('id', $memberId)->whereRaw('UPPER(status) = ?', ['ACTIVE'])->exists() && $creditAccount && $creditAccount->status === 'ACTIVE' && !$hasOverdue && $availableLimit > 0),
        ];
    }

    private function validateAndLockMemberCredit(string $memberId, string $totalAmount): void
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

        $outstanding = Receivable::where('member_id', $memberId)
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        $activeHolds = CreditHold::where('member_id', $memberId)
            ->where('status', 'ACTIVE')
            ->sum('amount');

        if (Money::minor($outstanding) + Money::minor($activeHolds) + Money::minor($totalAmount) > Money::minor($policy->getRawOriginal('limit_amount'))) {
            throw new BusinessRuleException('Limit piutang global tidak mencukupi untuk transaksi ini.', 422, 'CREDIT_LIMIT_EXCEEDED');
        }
    }

    private function syncOrderReadyForPickupIfPaid(Order $order): void
    {
        if ($order->payment_choice === 'CREDIT' || $order->status !== 'PENDING_PAYMENT') {
            return;
        }

        $verifiedSum = Payment::where('order_id', $order->id)
            ->where('status', 'VERIFIED')
            ->sum('amount');

        if (Money::minor($verifiedSum) >= Money::minor($order->getRawOriginal('total_amount'))) {
            $order->update(['status' => 'READY_FOR_PICKUP']);
        }
    }

    private function allocateOldestReceivables(string $memberId, int $remaining): array
    {
        $allocations = [];
        // Read IDs by due date, then lock selected receivables in consistent ID order.
        $receivables = Receivable::where('member_id', $memberId)->where('outstanding_amount', '>', 0)
            ->orderBy('id')->lockForUpdate()->get()->sortBy([
                fn ($left, $right) => $left->due_date->toDateString() <=> $right->due_date->toDateString(),
                fn ($left, $right) => strcmp((string) $left->id, (string) $right->id),
            ]);
        foreach ($receivables as $receivable) {
            if ($remaining === 0) break;
            $minor = min($remaining, Money::minor($receivable->getRawOriginal('outstanding_amount')));
            if ($minor > 0) {
                $allocations[] = ['receivable_id' => (string) $receivable->id, 'amount' => Money::decimal($minor)];
                $remaining -= $minor;
            }
        }
        if ($remaining !== 0 || $allocations === []) {
            throw new BusinessRuleException('Pembayaran melebihi saldo piutang.', 422, 'INVALID_PAYMENT');
        }
        return $allocations;
    }

    private function validateAllocationsBeforeRecording(array $allocations, string $memberId): void
    {
        $allocations = $this->canonicalizeAllocations($allocations);
        $seen = [];
        foreach ($allocations as $alloc) {
            if (isset($seen[$alloc['receivable_id']])) {
                throw new BusinessRuleException('Alokasi tagihan duplikat.', 422, 'INVALID_PAYMENT');
            }
            $seen[$alloc['receivable_id']] = true;
            $allocAmount = Money::amount($alloc['amount'] ?? 0);
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
            if (Money::minor($allocAmount) > Money::minor($receivable->getRawOriginal('outstanding_amount'))) {
                throw new BusinessRuleException('Nominal alokasi melebihi sisa saldo tagihan piutang.', 422, 'INVALID_PAYMENT');
            }
        }
    }

    private function applyReceivableAllocations(Payment $payment, array $allocations, string $memberId): void
    {
        if ($payment->status !== 'VERIFIED' || (string) $payment->member_id !== $memberId
            || $payment->allocations()->exists()
            || array_sum(array_map(static fn ($a) => Money::minor($a['amount']), $allocations)) !== Money::minor($payment->getRawOriginal('amount'))) {
            throw new BusinessRuleException('Alokasi pembayaran tidak konsisten atau telah diproses.', 422, 'INVALID_PAYMENT');
        }
        MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
        $this->validateAllocationsBeforeRecording($allocations, $memberId);
        $allocations = $this->canonicalizeAllocations($allocations);
        $seen = [];
        foreach ($allocations as $alloc) {
            if (isset($seen[$alloc['receivable_id']])) {
                throw new BusinessRuleException('Alokasi tagihan duplikat.', 422, 'INVALID_PAYMENT');
            }
            $seen[$alloc['receivable_id']] = true;
            $allocAmount = Money::amount($alloc['amount']);
            $receivable = Receivable::where('id', (string) $alloc['receivable_id'])->lockForUpdate()->first();

            if (!$receivable) {
                throw new BusinessRuleException('Tagihan piutang tidak ditemukan saat alokasi.', 404, 'RECEIVABLE_NOT_FOUND');
            }
            if ((string) $receivable->member_id !== $memberId) {
                throw new BusinessRuleException('Tagihan piutang tidak sesuai dengan anggota yang membayar.', 422, 'FORBIDDEN');
            }
            if (Money::minor($allocAmount) > Money::minor($receivable->getRawOriginal('outstanding_amount'))) {
                throw new BusinessRuleException('Nominal alokasi melebihi sisa saldo tagihan saat verifikasi.', 422, 'INVALID_PAYMENT');
            }

            ReceivablePaymentAllocation::create([
                'payment_id' => $payment->id,
                'receivable_id' => $receivable->id,
                'allocated_amount' => $allocAmount,
            ]);

            $newOutstanding = Money::decimal(Money::minor($receivable->getRawOriginal('outstanding_amount')) - Money::minor($allocAmount));
            $newStatus = ($newOutstanding <= 0) ? 'PAID' : 'PARTIALLY_PAID';

            // Jatuh tempo awal (due_date) tidak boleh diubah oleh pembayaran sebagian
            $receivable->update([
                'outstanding_amount' => $newOutstanding,
                'status' => $newStatus,
            ]);
        }
    }

    private function lockMatchingReservations(Order $order): \Illuminate\Support\Collection
    {
        $items = OrderItem::where('order_id', $order->id)->get();
        $reservations = StockReservation::where('order_id', $order->id)
            ->orderBy('product_id')->lockForUpdate()->get();
        if ($reservations->contains(fn ($row) => $row->status !== 'ACTIVE')) {
            throw new BusinessRuleException('Reservasi pesanan tidak seluruhnya aktif.', 422, 'INVALID_RESERVATION');
        }

        $quantities = static function ($rows): array {
            $result = [];
            foreach ($rows as $row) {
                $quantity = (int) $row->quantity;
                if ($quantity <= 0) {
                    throw new BusinessRuleException('Kuantitas reservasi atau item tidak valid.', 422, 'INVALID_RESERVATION');
                }
                $key = (string) $row->product_id;
                $result[$key] = ($result[$key] ?? 0) + $quantity;
            }
            ksort($result);
            return $result;
        };

        if ($items->isEmpty() || $quantities($items) !== $quantities($reservations)) {
            throw new BusinessRuleException('Reservasi stok tidak sesuai dengan item pesanan.', 422, 'INVALID_RESERVATION');
        }

        return $reservations;
    }

    private function lockRequiredCreditHold(Order $order): ?CreditHold
    {
        $hold = CreditHold::where('order_id', $order->id)->lockForUpdate()->first();
        if ($order->payment_choice !== 'CREDIT') {
            if ($hold && $hold->status === 'ACTIVE') {
                throw new BusinessRuleException('Pesanan nonkredit memiliki hold aktif yang tidak semestinya.', 422, 'INVALID_CREDIT_HOLD');
            }
            return null;
        }

        // Both values are DECIMAL(12,2); compare raw database values without float conversion.
        if (!$hold || $hold->status !== 'ACTIVE' || !$order->member_id
            || (string) $hold->member_id !== (string) $order->member_id
            || Money::minor($hold->getRawOriginal('amount')) !== Money::minor($order->getRawOriginal('total_amount'))
            || Money::minor($hold->amount) <= 0) {
            throw new BusinessRuleException('Credit hold aktif tidak sesuai dengan pesanan.', 422, 'INVALID_CREDIT_HOLD');
        }

        return $hold;
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

    private function resolveProductQuantities(array $items): array
    {
        $quantities = [];
        foreach ($items as $item) {
            $product = $this->findProduct((string) ($item['product_id'] ?? ''));
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (!$product) {
                throw new BusinessRuleException('Produk tidak ditemukan.', 404, 'PRODUCT_NOT_FOUND');
            }
            if ($quantity === false || $quantity <= 0) {
                throw new BusinessRuleException('Kuantitas tidak valid.', 422, 'INVALID_QUANTITY');
            }
            $id = (string) $product->id;
            $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
            if ($quantities[$id] > 2147483647) {
                throw new BusinessRuleException('Kuantitas melebihi batas.', 422, 'INVALID_QUANTITY');
            }
        }
        ksort($quantities);
        return array_map(static fn ($id, $qty) => ['product_id' => $id, 'quantity' => $qty], array_keys($quantities), array_values($quantities));
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
                'amount' => (float) Money::decimal(Money::minor($item['amount'] ?? 0)),
            ])
            ->sortBy('receivable_id')
            ->values()
            ->all();
    }

    private function generateUniquePickupCode(): string
    {
        // Serialize code assignment until the enclosing checkout transaction commits.
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(2074119)');
        }
        for ($attempt = 0; $attempt < 128; $attempt++) {
            $code = 'PKP-' . random_int(10000, 99999);
            if (!Order::where('pickup_code', $code)->exists()) {
                return $code;
            }
        }
        throw new BusinessRuleException('Kode pickup belum dapat dialokasikan. Hubungi pengelola atau coba lagi.', 409, 'PICKUP_CODE_UNAVAILABLE');
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
