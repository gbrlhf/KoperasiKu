<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Pastikan tabel members tersedia sesuai ERD v1.4
        if (!Schema::hasTable('members')) {
            Schema::create('members', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable()->unique();
                $table->string('employee_number', 50)->nullable()->unique();
                $table->string('member_number', 50)->nullable()->unique();
                $table->string('full_name', 150);
                $table->string('department', 100)->nullable();
                $table->string('status', 20)->default('ACTIVE');
                $table->timestamps();
            });
        }

        // Backfill members secara aman untuk database yang sudah memiliki data transaksi/akun kredit
        $this->backfillExistingMembers();

        // 2. Tabel audit perubahan kebijakan kredit global (credit_policy_logs)
        if (!Schema::hasTable('credit_policy_logs')) {
            Schema::create('credit_policy_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('credit_policy_id');
                $table->decimal('old_limit_amount', 12, 2)->nullable();
                $table->decimal('new_limit_amount', 12, 2);
                $table->boolean('old_active')->nullable();
                $table->boolean('new_active');
                $table->uuid('changed_by')->nullable();
                $table->string('reason', 255)->nullable();
                $table->timestamp('changed_at')->useCurrent();
                $table->timestamps();
            });
        }

        // 3. Tabel audit trail operasional & finansial (audit_logs)
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('actor_id')->nullable();
                $table->string('actor_role', 30)->nullable();
                $table->string('action', 100)->index();
                $table->string('entity_type', 100);
                $table->string('entity_id', 100)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['entity_type', 'entity_id']);
            });
        }

        // 4. Tambah kolom idempotency pada orders
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique();
            }
            if (!Schema::hasColumn('orders', 'pickup_idempotency_key')) {
                $table->string('pickup_idempotency_key', 100)->nullable()->unique();
            }
        });

        // 5. Izinkan order_items.hpp_snapshot NULL (untuk order pra-pickup & histori HPP tak diketahui)
        if (Schema::hasColumn('order_items', 'hpp_snapshot')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE order_items ALTER COLUMN hpp_snapshot DROP NOT NULL');
            } else {
                Schema::table('order_items', function (Blueprint $table) {
                    $table->decimal('hpp_snapshot', 12, 2)->nullable()->change();
                });
            }
        }

        // 6. Tambah kolom referensi transfer unik, idempotency, verified_at, & alokasi tertunda pada payments
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'external_reference')) {
                $table->string('external_reference', 100)->nullable()->unique();
            }
            if (!Schema::hasColumn('payments', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique();
            }
            if (!Schema::hasColumn('payments', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (!Schema::hasColumn('payments', 'pending_allocations')) {
                $table->json('pending_allocations')->nullable();
            }
        });

        // 7. Penguatan FK (RESTRICT menggantikan CASCADE di histori finansial) & CHECK constraints PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            // Hapus CASCADE DELETE pada histori finansial -> ganti RESTRICT
            $this->replaceCascadeWithRestrict('order_items', 'order_items_order_id_foreign', 'order_id', 'orders', 'id');
            $this->replaceCascadeWithRestrict('receivables', 'receivables_order_id_foreign', 'order_id', 'orders', 'id');
            $this->replaceCascadeWithRestrict('receivable_payment_allocations', 'receivable_payment_allocations_payment_id_foreign', 'payment_id', 'payments', 'id');
            $this->replaceCascadeWithRestrict('receivable_payment_allocations', 'receivable_payment_allocations_receivable_id_foreign', 'receivable_id', 'receivables', 'id');
            $this->replaceCascadeWithRestrict('stock_reservations', 'stock_reservations_order_id_foreign', 'order_id', 'orders', 'id');

            // Tambah FK yang belum terpasang (seluruhnya RESTRICT)
            $this->addForeignKeyIfMissing('members', 'members_user_id_foreign', 'user_id', 'users', 'id');
            $this->addForeignKeyIfMissing('orders', 'orders_member_id_foreign', 'member_id', 'members', 'id');
            $this->addForeignKeyIfMissing('order_items', 'order_items_product_id_foreign', 'product_id', 'products', 'id');
            $this->addForeignKeyIfMissing('inventory_balances', 'inventory_balances_product_id_foreign', 'product_id', 'products', 'id');
            $this->addForeignKeyIfMissing('stock_reservations', 'stock_reservations_product_id_foreign', 'product_id', 'products', 'id');
            $this->addForeignKeyIfMissing('stock_movements', 'stock_movements_product_id_foreign', 'product_id', 'products', 'id');
            $this->addForeignKeyIfMissing('member_credit_accounts', 'member_credit_accounts_member_id_foreign', 'member_id', 'members', 'id');
            $this->addForeignKeyIfMissing('credit_holds', 'credit_holds_order_id_foreign', 'order_id', 'orders', 'id');
            $this->addForeignKeyIfMissing('credit_holds', 'credit_holds_member_id_foreign', 'member_id', 'members', 'id');
            $this->addForeignKeyIfMissing('receivables', 'receivables_member_id_foreign', 'member_id', 'members', 'id');
            $this->addForeignKeyIfMissing('payments', 'payments_order_id_foreign', 'order_id', 'orders', 'id');
            $this->addForeignKeyIfMissing('payments', 'payments_member_id_foreign', 'member_id', 'members', 'id');
            $this->addForeignKeyIfMissing('payments', 'payments_verified_by_foreign', 'verified_by', 'users', 'id');
            $this->addForeignKeyIfMissing('products', 'products_category_id_foreign', 'category_id', 'categories', 'id');
            $this->addForeignKeyIfMissing('credit_policy_logs', 'credit_policy_logs_credit_policy_id_foreign', 'credit_policy_id', 'credit_policies', 'id');
            $this->addForeignKeyIfMissing('credit_policy_logs', 'credit_policy_logs_changed_by_foreign', 'changed_by', 'users', 'id');
            $this->addForeignKeyIfMissing('audit_logs', 'audit_logs_actor_id_foreign', 'actor_id', 'users', 'id');

            // CHECK constraints PostgreSQL
            $this->addCheckConstraintIfMissing('credit_policies', 'chk_credit_policies_valid', 'limit_amount >= 0 AND due_days = 7');
            $this->addCheckConstraintIfMissing('credit_holds', 'chk_credit_holds_positive', 'amount > 0');
            $this->addCheckConstraintIfMissing('inventory_balances', 'chk_inventory_balances_invariant', 'on_hand >= reserved AND reserved >= 0');
            $this->addCheckConstraintIfMissing('order_items', 'chk_order_items_valid', 'quantity > 0 AND unit_price_snapshot >= 0 AND (hpp_snapshot IS NULL OR hpp_snapshot >= 0)');
            $this->addCheckConstraintIfMissing('receivables', 'chk_receivables_valid', 'original_amount > 0 AND outstanding_amount >= 0');
            $this->addCheckConstraintIfMissing('payments', 'chk_payments_positive', 'amount > 0');
            $this->addCheckConstraintIfMissing('receivable_payment_allocations', 'chk_allocations_positive', 'allocated_amount > 0');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                ['credit_policies', 'chk_credit_policies_valid'],
                ['credit_holds', 'chk_credit_holds_positive'],
                ['inventory_balances', 'chk_inventory_balances_invariant'],
                ['order_items', 'chk_order_items_valid'],
                ['receivables', 'chk_receivables_valid'],
                ['payments', 'chk_payments_positive'],
                ['receivable_payment_allocations', 'chk_allocations_positive'],
            ] as [$table, $name]) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('credit_policy_logs');

        Schema::table('payments', function (Blueprint $table) {
            foreach (['external_reference', 'idempotency_key', 'pending_allocations'] as $col) {
                if (Schema::hasColumn('payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['idempotency_key', 'pickup_idempotency_key'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function backfillExistingMembers(): void
    {
        $memberIds = collect();

        foreach (['member_credit_accounts', 'credit_holds', 'receivables', 'orders', 'payments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'member_id')) {
                $ids = DB::table($table)->whereNotNull('member_id')->distinct()->pluck('member_id');
                $memberIds = $memberIds->merge($ids);
            }
        }

        $uniqueIds = $memberIds->unique()->values();
        if ($uniqueIds->isEmpty()) {
            return;
        }

        foreach ($uniqueIds as $memberId) {
            if (DB::table('members')->where('id', $memberId)->exists()) {
                continue;
            }

            $user = DB::table('users')->where('id', $memberId)->first();
            $compact = strtoupper(substr(str_replace('-', '', (string) $memberId), -6));
            $isDefaultAhmad = ((string) $memberId === '00000000-0000-0000-0000-000000000001');

            $empNo = $isDefaultAhmad ? 'EMP-001' : "EMP-{$compact}";
            $memNo = $isDefaultAhmad ? 'MEM-001' : "MEM-{$compact}";

            // Pastikan tidak bentrok bila employee_number/member_number sudah ada
            if (DB::table('members')->where('employee_number', $empNo)->exists()) {
                $empNo = "EMP-{$compact}-" . substr((string) crc32((string) $memberId), 0, 4);
            }
            if (DB::table('members')->where('member_number', $memNo)->exists()) {
                $memNo = "MEM-{$compact}-" . substr((string) crc32((string) $memberId), 0, 4);
            }

            $fullName = $isDefaultAhmad
                ? 'Ahmad Fauzi'
                : ($user ? ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $user->email)[0])) : "Anggota {$compact}");

            DB::table('members')->insert([
                'id' => $memberId,
                'user_id' => $user?->id,
                'employee_number' => $empNo,
                'member_number' => $memNo,
                'full_name' => $fullName,
                'department' => $isDefaultAhmad ? 'Teknik Mesin & Manufaktur POLMAN' : null,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function replaceCascadeWithRestrict(string $table, string $constraintName, string $column, string $refTable, string $refColumn): void
    {
        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$constraintName}') THEN
                    ALTER TABLE {$table} DROP CONSTRAINT {$constraintName};
                END IF;
                ALTER TABLE {$table}
                    ADD CONSTRAINT {$constraintName}
                    FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn}) ON DELETE RESTRICT;
            END $$;
        ");
    }

    private function addForeignKeyIfMissing(string $table, string $constraintName, string $column, string $refTable, string $refColumn): void
    {
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$constraintName}') THEN
                    ALTER TABLE {$table}
                        ADD CONSTRAINT {$constraintName}
                        FOREIGN KEY ({$column}) REFERENCES {$refTable}({$refColumn}) ON DELETE RESTRICT;
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
