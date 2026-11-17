<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Master Tabel Kategori Produk
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->string('icon')->default('bi-box');
                $table->timestamps();
            });
        }

        // 2. Master Tabel Produk (Sesuai PRD v1.4 Model Data)
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('sku')->unique();
                $table->string('barcode')->unique()->nullable();
                $table->string('name');
                $table->string('category')->default('Sembako');
                $table->uuid('category_id')->nullable();
                $table->decimal('selling_price', 12, 2);
                $table->decimal('original_price', 12, 2)->nullable();
                $table->decimal('current_cost', 12, 2)->default(0); // HPP Acuan
                $table->string('unit')->default('Pcs');
                $table->string('badge')->nullable();
                $table->string('image')->nullable();
                $table->decimal('rating', 3, 1)->default(5.0);
                $table->integer('reviews_count')->default(0);
                $table->integer('sales_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Tambahkan kolom pickup_code, order_no, dan total_amount ke tabel orders
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'order_no')) {
                $table->string('order_no')->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('orders', 'pickup_code')) {
                $table->string('pickup_code')->unique()->nullable()->after('order_no');
            }
            if (!Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('payment_choice');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_no', 'pickup_code', 'total_amount']);
        });
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
