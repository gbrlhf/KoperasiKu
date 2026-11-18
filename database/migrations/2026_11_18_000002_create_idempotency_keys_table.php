<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('idempotency_key', 100)->unique();
                $table->string('operation', 50);
                $table->uuid('user_id')->nullable();
                $table->string('target_resource_id', 100)->nullable();
                $table->string('request_hash', 64);
                $table->integer('response_status')->default(200);
                $table->json('response_body');
                $table->timestamps();

                $table->index(['user_id', 'operation']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
