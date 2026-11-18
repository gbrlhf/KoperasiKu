<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Guard keras: pengujian dilarang keras menyentuh database utama koperadata.
        if (DB::getDriverName() === 'pgsql' && DB::connection()->getDatabaseName() === 'koperadata') {
            throw new \RuntimeException('KESALAHAN KEAMANAN: Test mencoba terhubung ke database utama [koperadata]. Gunakan database terisolasi [koperadata_test].');
        }
    }
}
