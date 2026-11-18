<?php

require __DIR__ . '/../vendor/autoload.php';

// Paksa isolasi environment testing agar variabel shell/OS (misal DB_DATABASE=koperadata)
// tidak dapat membajak proses pengujian PostgreSQL terisolasi.
$forcedPgTestEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '5433',
    'DB_DATABASE' => 'koperadata_test',
    'DB_USERNAME' => 'postgres',
    'DB_PASSWORD' => 'secret',
    'DB_URL' => '',
];

foreach ($forcedPgTestEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
