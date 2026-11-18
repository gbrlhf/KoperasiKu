<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Kompatibilitas tabel categories yang sudah ada di root (jangan create ulang)
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'description')) {
                    $table->text('description')->nullable()->after('name');
                }
            });

            if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('categories', 'slug')) {
                DB::statement('ALTER TABLE categories ALTER COLUMN slug DROP NOT NULL');
            }
        }

        // 2. Master Supplier (PIC C)
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->string('phone', 30)->nullable();
                $table->text('address')->nullable();
                $table->integer('lead_time_days')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Kompatibilitas tabel products yang sudah ada di root (jangan create ulang)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'supplier_id')) {
                    $table->uuid('supplier_id')->nullable()->after('category_id');
                }
                if (!Schema::hasColumn('products', 'reorder_point')) {
                    $table->integer('reorder_point')->default(0)->after('current_cost');
                }
                if (!Schema::hasColumn('products', 'image_path')) {
                    $table->string('image_path')->nullable()->after('image');
                }
            });

            if (DB::getDriverName() === 'pgsql') {
                $this->addNullOnDeleteForeignKeyIfMissing('products', 'products_supplier_id_foreign', 'supplier_id', 'suppliers', 'id');
                $this->addCheckConstraintIfMissing(
                    'products',
                    'chk_products_non_negative',
                    'selling_price >= 0 AND current_cost >= 0 AND reorder_point >= 0'
                );
            }
        }

        // 4. Audit Riwayat Perubahan Harga & HPP (PIC C)
        if (!Schema::hasTable('product_price_histories')) {
            Schema::create('product_price_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_id');
                $table->decimal('old_cost', 12, 2);
                $table->decimal('new_cost', 12, 2);
                $table->decimal('old_sell_price', 12, 2);
                $table->decimal('new_sell_price', 12, 2);
                $table->uuid('changed_by')->nullable();
                $table->string('reason', 255);
                $table->timestamp('changed_at')->useCurrent();

                $table->index(['product_id', 'changed_at']);
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
                $table->foreign('changed_by')->references('id')->on('users')->onDelete('restrict');
            });
        }

        // 5. Penerimaan Pemasok & Detail Item (PIC C)
        if (!Schema::hasTable('purchase_receipts')) {
            Schema::create('purchase_receipts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('receipt_number', 64)->unique();
                $table->uuid('supplier_id')->nullable();
                $table->timestamp('received_at');
                $table->string('status', 30)->default('COMPLETED');
                $table->text('notes')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->index('received_at');
                $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            });
        }

        if (!Schema::hasTable('receipt_items')) {
            Schema::create('receipt_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('receipt_id');
                $table->uuid('product_id');
                $table->integer('quantity');
                $table->decimal('unit_cost', 12, 2);
                $table->timestamps();

                $table->foreign('receipt_id')->references('id')->on('purchase_receipts')->onDelete('restrict');
                $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            });
        }

        // 6. Biaya Operasional (PIC C)
        if (!Schema::hasTable('operating_expenses')) {
            Schema::create('operating_expenses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('created_by')->nullable();
                $table->date('expense_date');
                $table->decimal('amount', 12, 2);
                $table->string('category', 100);
                $table->text('description');
                $table->string('proof_path')->nullable();
                $table->timestamps();

                $table->index('expense_date');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            });
        }

        // 7. Tabel Staging Import Excel/CSV (PIC C)
        if (!Schema::hasTable('import_batches')) {
            Schema::create('import_batches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('uploaded_by')->nullable();
                $table->string('file_name', 255);
                $table->string('file_path', 255);
                $table->string('checksum', 64);
                $table->string('import_type', 50);
                $table->string('status', 30)->default('UPLOADED');
                $table->timestamp('cutoff_at')->nullable();
                $table->integer('total_rows')->default(0);
                $table->integer('valid_rows')->default(0);
                $table->integer('error_rows')->default(0);
                $table->integer('duplicate_rows')->default(0);
                $table->json('column_mapping')->nullable();
                $table->json('quality_metadata')->nullable();
                $table->timestamp('committed_at')->nullable();
                $table->timestamps();

                $table->index(['checksum', 'import_type']);
                $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('restrict');
            });
        }

        if (!Schema::hasTable('import_rows')) {
            Schema::create('import_rows', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('batch_id');
                $table->integer('row_number');
                $table->string('row_status', 30)->default('PENDING');
                $table->string('source_key', 150)->nullable();
                $table->json('raw_data');
                $table->json('normalized_data')->nullable();
                $table->timestamps();

                $table->unique(['batch_id', 'row_number']);
                $table->foreign('batch_id')->references('id')->on('import_batches')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('import_errors')) {
            Schema::create('import_errors', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('import_row_id');
                $table->string('field_name', 100)->nullable();
                $table->string('error_code', 50);
                $table->text('error_message');
                $table->timestamps();

                $table->foreign('import_row_id')->references('id')->on('import_rows')->onDelete('cascade');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            $this->addCheckConstraintIfMissing('suppliers', 'chk_suppliers_lead_time', 'lead_time_days >= 1');
            $this->addCheckConstraintIfMissing(
                'product_price_histories',
                'chk_product_price_histories_non_negative',
                'old_cost >= 0 AND new_cost >= 0 AND old_sell_price >= 0 AND new_sell_price >= 0'
            );
            $this->addCheckConstraintIfMissing('receipt_items', 'chk_receipt_items_valid', 'quantity > 0 AND unit_cost >= 0');
            $this->addCheckConstraintIfMissing('operating_expenses', 'chk_operating_expenses_positive', 'amount > 0');

            // B7.2 Immutability Guard di level PostgreSQL: order_items.hpp_snapshot pada order COMPLETED tidak dapat diubah
            DB::unprepared("
                CREATE OR REPLACE FUNCTION fn_protect_completed_order_item_hpp()
                RETURNS TRIGGER AS $$
                DECLARE
                    parent_status VARCHAR;
                BEGIN
                    IF OLD.hpp_snapshot IS DISTINCT FROM NEW.hpp_snapshot THEN
                        SELECT status INTO parent_status FROM orders WHERE id = NEW.order_id;
                        IF parent_status = 'COMPLETED' THEN
                            RAISE EXCEPTION 'IMMUTABLE_HPP_SNAPSHOT: hpp_snapshot pada order COMPLETED tidak boleh diubah.';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_protect_completed_order_item_hpp ON order_items;
                CREATE TRIGGER trg_protect_completed_order_item_hpp
                    BEFORE UPDATE ON order_items
                    FOR EACH ROW
                    EXECUTE FUNCTION fn_protect_completed_order_item_hpp();
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('
                DROP TRIGGER IF EXISTS trg_protect_completed_order_item_hpp ON order_items;
                DROP FUNCTION IF EXISTS fn_protect_completed_order_item_hpp();
            ');
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_products_non_negative');
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_supplier_id_foreign');
        }

        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('operating_expenses');
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('product_price_histories');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                foreach (['supplier_id', 'reorder_point', 'image_path'] as $col) {
                    if (Schema::hasColumn('products', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('suppliers');

        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'description')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }

    private function addNullOnDeleteForeignKeyIfMissing(string $table, string $constraintName, string $column, string $refTable, string $refColumn): void
    {
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$constraintName}') THEN
                    ALTER TABLE {$table}
                        ADD CONSTRAINT {$constraintName}
                        FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn}) ON DELETE SET NULL;
                END IF;
            END $$;
        ");
    }

    private function addCheckConstraintIfMissing(string $table, string $constraintName, string $expression): void
    {
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$constraintName}') THEN
                    ALTER TABLE {$table}
                        ADD CONSTRAINT {$constraintName} CHECK ({$expression});
                END IF;
            END $$;
        ");
    }
};
