<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') === 'pgsql') {
            $connection = $app['config']->get('database.connections.pgsql');
            if (($connection['database'] ?? null) !== 'koperadata_test' || !empty($connection['url'])
                || !in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)) {
                throw new \RuntimeException('KESALAHAN KEAMANAN: gunakan database PostgreSQL lokal koperadata_test.');
            }
        }
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Guard keras: pengujian dilarang keras menyentuh database utama koperadata.
        if (DB::getDriverName() === 'pgsql' && DB::connection()->getDatabaseName() !== 'koperadata_test') {
            throw new \RuntimeException('KESALAHAN KEAMANAN: Test mencoba terhubung ke database utama [koperadata]. Gunakan database terisolasi [koperadata_test].');
        }
    }
}
