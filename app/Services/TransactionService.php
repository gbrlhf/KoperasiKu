<?php
namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\InventoryBalance;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Models\CreditHold;
use App\Models\Receivable;
use App\Models\Payment;
use App\Models\CreditPolicy;
use App\Models\MemberCreditAccount;
use App\Models\ReceivablePaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TransactionService
{
    public function checkout(array $data, string $channel = 'ONLINE_PICKUP')
    {
        return DB::transaction(function () use ($data, $channel) {
            $memberId = $data['member_id'] ?? null;
            $paymentChoice = $data['payment_choice']; 
            $items = $data['items']; 

            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($items as $item) {
                $product = $this->findProduct((string) $item['product_id']);
                if (!$product) {
                    throw new \Exception("Produk tidak ditemukan di master katalog: {$item['product_id']}", 404);
                }

                $inventory = InventoryBalance::where('product_id', $product->id)->lockForUpdate()->first();
                if (!$inventory) {
                    $inventory = InventoryBalance::create([
                        'product_id' => $product->id,
                        'on_hand' => 50,
                        'reserved' => 0,
                    ]);
                }
                
                if (($inventory->on_hand - $inventory->reserved) < $item['quantity']) {
                    throw new \Exception("Stok tidak mencukupi untuk produk: {$product->name}", 422);
                }

                $subtotal = $product->selling_price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price_snapshot' => $product->selling_price,
                    'hpp_snapshot' => $product->current_cost,
                ];
            }

            if ($paymentChoice === 'CREDIT') {
                if (!$memberId) {
                    throw new \Exception("Pembelian kredit memerlukan identitas anggota.", 422);
                }

                $creditAccount = MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
                if (!$creditAccount || $creditAccount->status !== 'ACTIVE') {
                    throw new \Exception("Fasilitas kredit anggota tidak aktif atau diblokir.", 422);
                }

                $hasOverdue = Receivable::where('member_id', $memberId)
                    ->where('outstanding_amount', '>', 0)
                    ->where('due_date', '<', Carbon::now('Asia/Jakarta')->toDateString())
                    ->exists();

                if ($hasOverdue) {
                    throw new \Exception("Anggota memiliki tunggakan piutang lewat jatuh tempo. Kredit baru ditolak.", 422);
                }

                $policy = CreditPolicy::where('active', true)->latest('effective_at')->first();
                if (!$policy) {
                    throw new \Exception("Kebijakan limit global belum dikonfigurasi oleh Manajer.", 500);
                }

                $outstanding = Receivable::where('member_id', $memberId)->whereIn('status', ['OPEN', 'PARTIALLY_PAID', 'OVERDUE'])->sum('outstanding_amount');
                $activeHolds = CreditHold::where('member_id', $memberId)->where('status', 'ACTIVE')->sum('amount');

                if (($outstanding + $activeHolds + $totalAmount) > $policy->limit_amount) {
                    throw new \Exception("Limit piutang global tidak mencukupi untuk transaksi ini.", 422);
                }
            }

            $pickupCode = 'PKP-' . mt_rand(10000, 99999);
            $orderNo = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $order = Order::create([
                'member_id' => $memberId,
                'channel' => $channel,
                'status' => ($paymentChoice === 'CREDIT') ? 'PENDING_PICKUP' : 'PENDING_PAYMENT',
                'payment_choice' => $paymentChoice,
                'order_no' => $orderNo,
                'pickup_code' => $pickupCode,
                'total_amount' => $totalAmount,
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

            return $order;
        });
    }

    public function confirmPickup(string $orderId)
    {
        return DB::transaction(function () use ($orderId) {
            $cleanOrderId = trim($orderId);
            $isUuid = \Illuminate\Support\Str::isUuid($cleanOrderId);

            $query = Order::lockForUpdate();
            if ($isUuid) {
                $query->where('id', $cleanOrderId)
                    ->orWhere('pickup_code', $cleanOrderId)
                    ->orWhere('order_no', $cleanOrderId);
            } else {
                $query->where('pickup_code', $cleanOrderId)
                    ->orWhere('order_no', $cleanOrderId);
            }

            $order = $query->first();

            if (!$order) {
                throw new \Exception("Pesanan dengan ID / Kode Pickup '{$orderId}' tidak ditemukan.", 404);
            }

            if ($order->status === 'COMPLETED') {
                throw new \Exception("Pesanan sudah diselesaikan sebelumnya (Idempotent).", 400);
            }

            $reservations = StockReservation::where('order_id', $order->id)->get();

            foreach ($reservations as $res) {
                $inventory = InventoryBalance::where('product_id', $res->product_id)->lockForUpdate()->first();
                $inventory->decrement('on_hand', $res->quantity);
                $inventory->decrement('reserved', $res->quantity);

                StockMovement::create([
                    'product_id' => $res->product_id,
                    'quantity_change' => -$res->quantity,
                    'type' => 'SALE_PICKUP',
                    'reference_id' => $order->id,
                    'reason' => 'Serah terima pesanan selesai',
                ]);
            }

            StockReservation::where('order_id', $order->id)->delete();

            $creditHold = CreditHold::where('order_id', $order->id)->first();
            if ($creditHold) {
                $creditHold->update(['status' => 'COMMITTED']);
            }

            $orderItems = OrderItem::where('order_id', $order->id)->get();
            $totalOrderAmount = $orderItems->sum(fn($i) => $i->unit_price_snapshot * $i->quantity);

            $receivableId = null;
            if ($order->payment_choice === 'CREDIT') {
                $dueDate = Carbon::now('Asia/Jakarta')->addDays(7)->toDateString();

                $receivable = Receivable::create([
                    'order_id' => $order->id,
                    'member_id' => $order->member_id,
                    'original_amount' => $totalOrderAmount,
                    'outstanding_amount' => $totalOrderAmount,
                    'due_date' => $dueDate,
                    'status' => 'OPEN',
                ]);
                $receivableId = $receivable->id;
            }

            $order->update([
                'status' => 'COMPLETED',
                'completed_at' => Carbon::now('Asia/Jakarta'),
            ]);

            return [
                'order_id' => $order->id,
                'status' => 'COMPLETED',
                'completed_at' => $order->completed_at,
                'receivable_id' => $receivableId,
            ];
        });
    }

    public function posSale(array $data)
    {
        return DB::transaction(function () use ($data) {
            $memberId = $data['member_id'] ?? null;
            $paymentChoice = $data['payment_choice']; 
            $items = $data['items'];

            if (!$memberId && $paymentChoice === 'CREDIT') {
                throw new \Exception("Penjualan POS untuk non-anggota wajib lunas (Tunai/Transfer).", 422);
            }

            $totalAmount = 0;
            $orderItemsData = [];
            $inventoryUpdates = [];

            foreach ($items as $item) {
                $product = $this->findProduct((string) $item['product_id']);
                if (!$product) {
                    throw new \Exception("Produk tidak ditemukan di master katalog: {$item['product_id']}", 404);
                }

                $inventory = InventoryBalance::where('product_id', $product->id)->lockForUpdate()->first();
                if (!$inventory) {
                    $inventory = InventoryBalance::create([
                        'product_id' => $product->id,
                        'on_hand' => 50,
                        'reserved' => 0,
                    ]);
                }
                
                if ($inventory->on_hand < $item['quantity']) {
                    throw new \Exception("Stok fisik tidak mencukupi untuk produk: {$product->name}", 422);
                }

                $subtotal = $product->selling_price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price_snapshot' => $product->selling_price,
                    'hpp_snapshot' => $product->current_cost,
                ];

                $inventoryUpdates[] = [
                    'inventory' => $inventory,
                    'quantity' => $item['quantity'],
                ];
            }

            if ($paymentChoice === 'CREDIT') {
                $creditAccount = MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
                if (!$creditAccount || $creditAccount->status !== 'ACTIVE') {
                    throw new \Exception("Fasilitas kredit anggota tidak aktif atau diblokir.", 422);
                }

                $hasOverdue = Receivable::where('member_id', $memberId)
                    ->where('outstanding_amount', '>', 0)
                    ->where('due_date', '<', Carbon::now('Asia/Jakarta')->toDateString())
                    ->exists();

                if ($hasOverdue) {
                    throw new \Exception("Anggota memiliki tunggakan piutang lewat jatuh tempo. Kredit POS ditolak.", 422);
                }

                $policy = CreditPolicy::where('active', true)->latest('effective_at')->first();
                if (!$policy) {
                    throw new \Exception("Kebijakan limit global belum dikonfigurasi oleh Manajer.", 500);
                }

                $outstanding = Receivable::where('member_id', $memberId)->whereIn('status', ['OPEN', 'PARTIALLY_PAID', 'OVERDUE'])->sum('outstanding_amount');
                $activeHolds = CreditHold::where('member_id', $memberId)->where('status', 'ACTIVE')->sum('amount');

                if (($outstanding + $activeHolds + $totalAmount) > $policy->limit_amount) {
                    throw new \Exception("Limit piutang global tidak mencukupi untuk transaksi POS ini.", 422);
                }
            }

            $order = Order::create([
                'member_id' => $memberId,
                'channel' => 'POS',
                'status' => 'COMPLETED',
                'payment_choice' => $paymentChoice,
                'completed_at' => Carbon::now('Asia/Jakarta'),
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
                $dueDate = Carbon::now('Asia/Jakarta')->addDays(7)->toDateString();

                $receivable = Receivable::create([
                    'order_id' => $order->id,
                    'member_id' => $memberId,
                    'original_amount' => $totalAmount,
                    'outstanding_amount' => $totalAmount,
                    'due_date' => $dueDate,
                    'status' => 'OPEN',
                ]);
                $receivableId = $receivable->id;
            } else {
                Payment::create([
                    'order_id' => $order->id,
                    'member_id' => $memberId,
                    'amount' => $totalAmount,
                    'method' => $paymentChoice,
                    'status' => 'VERIFIED',
                ]);
            }

            return [
                'order_id' => $order->id,
                'status' => 'COMPLETED',
                'channel' => 'POS',
                'total_amount' => $totalAmount,
                'receivable_id' => $receivableId,
            ];
        });
    }

    public function payReceivable(array $data)
    {
        return DB::transaction(function () use ($data) {
            $memberId = $data['member_id'];
            $method = $data['method']; 
            $amount = $data['amount'];
            $allocations = $data['allocations']; 

            $totalAllocated = collect($allocations)->sum('amount');
            if ($totalAllocated != $amount) {
                throw new \Exception("Total alokasi pembayaran tidak sesuai dengan nominal pembayaran.", 422);
            }

            $paymentStatus = ($method === 'CASH') ? 'VERIFIED' : 'PENDING_VERIFICATION';
            
            $payment = Payment::create([
                'member_id' => $memberId,
                'amount' => $amount,
                'method' => $method,
                'status' => $paymentStatus,
            ]);

            if ($paymentStatus === 'VERIFIED') {
                foreach ($allocations as $alloc) {
                    $receivable = Receivable::lockForUpdate()->findOrFail($alloc['receivable_id']);

                    if ($receivable->member_id !== $memberId) {
                        throw new \Exception("Tagihan piutang tidak sesuai dengan anggota yang membayar.", 422);
                    }

                    if ($alloc['amount'] > $receivable->outstanding_amount) {
                        throw new \Exception("Nominal alokasi melebihi sisa saldo tagihan piutang.", 422);
                    }

                    ReceivablePaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'receivable_id' => $receivable->id,
                        'allocated_amount' => $alloc['amount'],
                    ]);

                    $newOutstanding = $receivable->outstanding_amount - $alloc['amount'];
                    $newStatus = ($newOutstanding <= 0) ? 'PAID' : 'PARTIALLY_PAID';

                    $receivable->update([
                        'outstanding_amount' => $newOutstanding,
                        'status' => $newStatus,
                    ]);
                }
            }

            return [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => $amount,
                'message' => $paymentStatus === 'VERIFIED' 
                    ? 'Pembayaran cicilan berhasil dicatat dan piutang telah dipotong.' 
                    : 'Bukti transfer berhasil diunggah dan menunggu verifikasi petugas.',
            ];
        });
    }

    public function cancelOrder(string $orderId)
    {
        return DB::transaction(function () use ($orderId) {
            $cleanOrderId = trim($orderId);
            $isUuid = \Illuminate\Support\Str::isUuid($cleanOrderId);

            $query = Order::lockForUpdate();
            if ($isUuid) {
                $query->where('id', $cleanOrderId)
                    ->orWhere('pickup_code', $cleanOrderId)
                    ->orWhere('order_no', $cleanOrderId);
            } else {
                $query->where('pickup_code', $cleanOrderId)
                    ->orWhere('order_no', $cleanOrderId);
            }

            $order = $query->first();

            if (!$order) {
                throw new \Exception("Pesanan dengan ID / Kode '{$orderId}' tidak ditemukan.", 404);
            }

            if ($order->status === 'COMPLETED') {
                throw new \Exception("Pesanan yang sudah selesai (COMPLETED) tidak dapat dibatalkan.", 400);
            }

            if ($order->status === 'CANCELLED') {
                throw new \Exception("Pesanan sudah dibatalkan sebelumnya (Idempotent).", 400);
            }

            $reservations = StockReservation::where('order_id', $order->id)->get();

            foreach ($reservations as $res) {
                $inventory = InventoryBalance::where('product_id', $res->product_id)->lockForUpdate()->first();
                if ($inventory) {
                    $inventory->decrement('reserved', $res->quantity);
                }
            }

            StockReservation::where('order_id', $order->id)->delete();

            $creditHold = CreditHold::where('order_id', $order->id)->first();
            if ($creditHold && $creditHold->status === 'ACTIVE') {
                $creditHold->update(['status' => 'RELEASED']);
            }

            $order->update([
                'status' => 'CANCELLED',
            ]);

            return [
                'order_id' => $order->id,
                'status' => 'CANCELLED',
                'message' => 'Pesanan berhasil dibatalkan, reservasi stok dan hold kredit telah dilepas.',
            ];
        });
    }

    /**
     * Helper fleksibel untuk mencari produk berdasarkan ID, SKU, PRD-code, atau Barcode
     */
    public function findProduct(string $identifier)
    {
        $cleanId = trim($identifier);
        $isUuid = \Illuminate\Support\Str::isUuid($cleanId);

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

        $result = $query->first();

        if (!$result && str_starts_with(strtoupper($cleanId), 'PRD-')) {
            $prdMap = [
                'PRD-001' => 'SEM-001',
                'PRD-002' => 'MNY-001',
                'PRD-003' => 'GUL-001',
                'PRD-004' => 'TLR-001',
                'PRD-005' => 'SSU-001',
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
                $result = DB::table('products')->where('sku', $sku)->first();
            }
        }

        return $result;
    }
}