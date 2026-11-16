<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Anggota (C-01)
        Schema::create('members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->unique();
            $table->string('employee_number', 50)->unique();
            $table->string('member_number', 50)->unique();
            $table->string('full_name', 150);
            $table->string('department', 100)->nullable();
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE, INACTIVE
            $table->timestamps();
        });

        // 2. Master Kategori (C-02)
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 3. Master Supplier (C-03)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->integer('lead_time_days')->default(1); // TODO POLICY: default lead time per supplier
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Master Produk (C-02, C-04)
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable();
            $table->string('name', 200);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('selling_price', 18, 2);
            $table->decimal('current_cost', 18, 2)->default(0); // HPP acuan (hanya boleh dilihat MANAGER)
            $table->integer('reorder_point')->default(0); // TODO POLICY: safety stock / reorder point
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX uq_products_barcode_not_null ON products (barcode) WHERE barcode IS NOT NULL');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_non_negative CHECK (selling_price >= 0 AND current_cost >= 0 AND reorder_point >= 0)');

        // Tabel bayangan inventory_balances agar PIC C bisa test mandiri sebelum integrasi dengan PIC B
        if (!Schema::hasTable('inventory_balances')) {
            Schema::create('inventory_balances', function (Blueprint $table) {
                $table->uuid('product_id')->primary();
                $table->integer('on_hand')->default(0);
                $table->integer('reserved')->default(0);
                $table->timestamps();
            });
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inv_non_negative CHECK (on_hand >= reserved AND reserved >= 0)');
        }

        // 5. Audit Riwayat Perubahan Harga & HPP (C-05)
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('old_cost', 18, 2);
            $table->decimal('new_cost', 18, 2);
            $table->decimal('old_sell_price', 18, 2);
            $table->decimal('new_sell_price', 18, 2);
            $table->uuid('changed_by');
            $table->string('reason', 255);
            $table->timestamp('changed_at')->useCurrent();
        });

        // 6. Penerimaan Pemasok (C-06)
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_number', 64)->unique();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->string('status', 30)->default('COMPLETED');
            $table->text('notes')->nullable();
            $table->uuid('created_by');
            $table->timestamps();
        });

        Schema::create('receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('purchase_receipts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 18, 2);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE receipt_items ADD CONSTRAINT chk_receipt_items_valid CHECK (quantity > 0 AND unit_cost >= 0)');

        // 7. Biaya Operasional (C-07)
        Schema::create('operating_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('created_by');
            $table->date('expense_date');
            $table->decimal('amount', 18, 2);
            $table->string('category', 100);
            $table->text('description');
            $table->string('proof_path')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE operating_expenses ADD CONSTRAINT chk_operating_expenses_positive CHECK (amount > 0)');

        // 8. Staging Import Excel/CSV (C-08 s.d. C-17)
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uploaded_by');
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('checksum', 64);
            $table->string('import_type', 50); // MEMBERS, PRODUCTS, STOCK_OPENING, SALES_HISTORY, SUPPLIERS
            $table->string('status', 30)->default('UPLOADED'); // UPLOADED, VALIDATING, PREVIEW_READY, COMMITTED, FAILED, REQUIRES_REVIEW
            $table->timestamp('cutoff_at')->nullable(); // TODO POLICY: tanggal cut-off data Excel
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->integer('duplicate_rows')->default(0);
            $table->jsonb('column_mapping')->nullable();
            $table->jsonb('quality_metadata')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['checksum', 'import_type']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->integer('row_number');
            $table->string('row_status', 30)->default('PENDING'); // VALID, INVALID, DUPLICATE, REQUIRES_REVIEW
            $table->string('source_key', 150)->nullable();
            $table->jsonb('raw_data');
            $table->jsonb('normalized_data')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'row_number']);
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_row_id')->constrained('import_rows')->cascadeOnDelete();
            $table->string('field_name', 100)->nullable();
            $table->string('error_code', 50);
            $table->text('error_message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('operating_expenses');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('product_price_histories');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('members');
    }
};