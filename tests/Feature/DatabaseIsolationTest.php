<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_postgresql_testing_uses_isolated_koperadata_test_database(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
        $this->assertSame('koperadata_test', DB::connection()->getDatabaseName());
        $this->assertNotSame('koperadata', DB::connection()->getDatabaseName());
    }

    public function test_database_isolation_guard_rejects_koperadata_connection(): void
    {
        $original = config('database.connections.pgsql.database');

        try {
            config(['database.connections.pgsql.database' => 'koperadata']);
            DB::purge('pgsql');

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('KESALAHAN KEAMANAN');
            $this->setUp();
        } finally {
            config(['database.connections.pgsql.database' => $original]);
            DB::purge('pgsql');
        }
    }
}
