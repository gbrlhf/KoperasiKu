# KOPERASIKU — HPP & PIC C INTEGRATION CONTRACT (FASE B7.4)

Dokumen ini menetapkan kontrak arsitektur, batas transaksi database, urutan *row-locking*, dan kompatibilitas skema antara Core Transaksi (`FASE B`) dan Modul Master Data / Purchase Receipt / Impor (`FASE C`).

## 1. Alur End-to-End HPP & Stok

```text
Purchase Receipt (POST /api/v1/inventory/receipts)
  └──► Receipt Validation (quantity > 0, unit_cost >= 0, unique receipt_number)
         └──► Single Atomic DB::transaction()
                ├── 1. Lock row `products` (FOR UPDATE, urut by product_id)
                ├── 2. Read & Lock `inventory_balances` via `InventoryServiceInterface::getOnHand()`
                ├── 3. Moving Weighted Average Cost:
                │      new_cost = round(((on_hand * old_cost) + (incoming_qty * unit_cost)) / (on_hand + incoming_qty), 2)
                ├── 4. Write `product_price_histories` (jika new_cost != old_cost) & update `products.current_cost`
                ├── 5. Update `inventory_balances.on_hand += incoming_qty` & tulis `stock_movements` (`PURCHASE_RECEIPT`)
                │      via `InventoryServiceInterface::receive()` (tanpa menyentuh `reserved`)
                └── 6. Commit atomik
                       │
                       ▼
              Future Transactions:
                ├── Online Checkout: `unit_price_snapshot = selling_price`, `hpp_snapshot = NULL`, `reserved += qty`
                ├── Online Pickup (`confirmPickup`): Lock `products` -> `inventory_balances` -> kurangi `on_hand` & `reserved`,
                │                                    snapshot `order_items.hpp_snapshot = products.current_cost`,
                │                                    set `orders.status = 'COMPLETED'`
                └── POS Direct Sale (`posSale`): Lock `products` -> `inventory_balances` -> kurangi `on_hand`,
                                                 snapshot `unit_price_snapshot` & `hpp_snapshot = products.current_cost`,
                                                 set `orders.status = 'COMPLETED'`
```

## 2. Kontrak Interface Inventori (`App\Contracts\InventoryServiceInterface`)

Implementasi root: `App\Services\InventoryService` (terikat di `AppServiceProvider`):
- `receive(string $productId, int $quantity, string $reason, string $actorUserId, string $sourceReference): void`
  - Memvalidasi `$quantity > 0`.
  - Mengunci `products` lebih dulu lalu `inventory_balances` (`lockForUpdate`).
  - Menambah `on_hand` tanpa mengubah `reserved`.
  - Mencatat `stock_movements` (`PURCHASE_RECEIPT` / `ADJUSTMENT`).
- `getOnHand(string $productId): int`
  - Jika dipanggil di dalam `DB::transaction()`, menggunakan `lockForUpdate()` untuk mencegah *stale read* saat hitung Weighted Average Cost.
- `getAvailable(string $productId): int`
  - Menghitung `max(0, on_hand - reserved)` sebagai satu sumber kebenaran ketersediaan stok.

## 3. Tata Kelola Concurrency & Deadlock Prevention

Seluruh layanan (`TransactionService`, `InventoryService`, dan `WeightedAverageCostService` di FASE C) **wajib** memperoleh *row lock* dengan hierarki kanonik:
1. `products` (`ORDER BY product_id`, `FOR UPDATE`)
2. `inventory_balances` (`ORDER BY product_id`, `FOR UPDATE`)

## 4. Immutabilitas HPP Historis

1. `order_items.hpp_snapshot` tetap `NULL` pada order pra-pickup (`PENDING_PAYMENT`, `PENDING_PICKUP`, `READY_FOR_PICKUP`) dan transaksi historis lama yang HPP-nya tidak diketahui.
2. Setelah `orders.status = 'COMPLETED'`, `hpp_snapshot` tidak dapat diubah ulang:
   - **Application Layer:** `OrderItem::booted()` menolak perubahan `hpp_snapshot` pada order `COMPLETED` (`409 IMMUTABLE_HPP_SNAPSHOT`).
   - **Database Layer:** Trigger PostgreSQL `trg_protect_completed_order_item_hpp` menolak `UPDATE order_items.hpp_snapshot` jika status `orders` adalah `'COMPLETED'`.
3. Perubahan `products.current_cost` dari penerimaan pemasok baru **hanya** memengaruhi transaksi `COMPLETED` di masa depan.
