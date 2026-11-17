<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Kebijakan Kredit Global (Satu limit untuk semua anggota eligible)[cite: 4]
        Schema::create('credit_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('limit_amount', 12, 2); 
            $table->integer('due_days')->default(7); // Jatuh tempo 7 hari kalender[cite: 4]
            $table->boolean('active')->default(true);
            $table->timestamp('effective_at');
            $table->timestamps();
        });

        Schema::create('member_credit_accounts', function (Blueprint $table) {
            $table->uuid('member_id')->primary();
            $table->string('status')->default('ACTIVE'); // ACTIVE / BLOCKED
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        // 2. Credit Holds (Penahanan limit sementara saat checkout kredit)[cite: 4]
        Schema::create('credit_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->uuid('member_id');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('ACTIVE'); // ACTIVE, RELEASED, COMMITTED
            $table->timestamps();
        });

        // 3. Orders & Order Items
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id')->nullable(); // Nullable untuk POS non-anggota lunas
            $table->string('channel'); // ONLINE_PICKUP atau POS
            $table->string('status')->default('PENDING_PAYMENT'); 
            // PENDING_PAYMENT, PENDING_PICKUP, READY_FOR_PICKUP, COMPLETED, CANCELLED
            $table->string('payment_choice'); // CASH, BANK_TRANSFER, CREDIT
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->integer('quantity');
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->decimal('hpp_snapshot', 12, 2);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });

        // 4. Inventory & Stock Management (Invariant: available = on_hand - reserved)[cite: 4]
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->uuid('product_id')->primary();
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->timestamps();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->integer('quantity');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->integer('quantity_change');
            $table->string('type'); // PURCHASE_RECEIPT, SALE_PICKUP, ADJUSTMENT
            $table->uuid('reference_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // 5. Receivables & Payments (Piutang & Alokasi Pembayaran Cicilan)[cite: 4]
        Schema::create('receivables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->unique();
            $table->uuid('member_id');
            $table->decimal('original_amount', 12, 2);
            $table->decimal('outstanding_amount', 12, 2);
            $table->date('due_date'); // Tepat 7 hari kalender[cite: 4]
            $table->string('status')->default('OPEN'); // OPEN, PARTIALLY_PAID, PAID, OVERDUE
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->uuid('member_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('method'); // CASH, BANK_TRANSFER
            $table->string('status')->default('PENDING_VERIFICATION'); // PENDING_VERIFICATION, VERIFIED, REJECTED
            $table->uuid('verified_by')->nullable();
            $table->timestamps();
        });

        Schema::create('receivable_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('receivable_id');
            $table->decimal('allocated_amount', 12, 2);
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('receivable_id')->references('id')->on('receivables')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('receivables');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('credit_holds');
        Schema::dropIfExists('member_credit_accounts');
        Schema::dropIfExists('credit_policies');
    }
};
