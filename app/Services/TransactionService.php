<?php
namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\InventoryBalance;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Models\CreditHold;
use App\Models\Receivable;
use App\Models\CreditPolicy;
use App\Models\MemberCreditAccount;
use Illuminate\Support\Facades\DB;
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

            // Validasi Stok Tersedia (available = on_hand - reserved)[cite: 4]
            foreach ($items as $item) {
                $inventory = InventoryBalance::where('product_id', $item['product_id'])->lockForUpdate()->first();
                
                if (!$inventory || ($inventory->on_hand - $inventory->reserved) < $item['quantity']) {
                    throw new \Exception("Stok tidak mencukupi untuk produk ID: {$item['product_id']}", 422);
                }

                $product = DB::table('products')->where('id', $item['product_id'])->first();
                $subtotal = $product->selling_price * $item['quantity'];
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price_snapshot' => $product->selling_price,
                    'hpp_snapshot' => $product->current_cost,
                ];
            }

            // Validasi Limit Global Piutang jika metode CREDIT[cite: 4]
            if ($paymentChoice === 'CREDIT') {
                if (!$memberId) {
                    throw new \Exception("Pembelian kredit memerlukan identitas anggota.", 422);
                }

                $creditAccount = MemberCreditAccount::where('member_id', $memberId)->lockForUpdate()->first();
                if (!$creditAccount || $creditAccount->status !== 'ACTIVE') {
                    throw new \Exception("Fasilitas kredit anggota tidak aktif atau diblokir.", 422);
                }

                $hasOverdue = Receivable::where('member_id', $memberId)->where('status', 'OVERDUE')->exists();
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

            $order = Order::create([
                'member_id' => $memberId,
                'channel' => $channel,
                'status' => ($paymentChoice === 'CREDIT') ? 'PENDING_PICKUP' : 'PENDING_PAYMENT',
                'payment_choice' => $paymentChoice,
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
            $order = Order::lockForUpdate()->findOrFail($orderId);

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
                $dueDate = Carbon::now('Asia/Jakarta')->addDays(7)->toDateString(); // Jatuh tempo 7 hari[cite: 4]

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
}