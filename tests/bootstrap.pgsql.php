<?php

require __DIR__ . '/../vendor/autoload.php';

// Only read the dedicated testing file, never the development .env.
$path = dirname(__DIR__) . '/.env.testing';
$local = is_file($path) ? \Dotenv\Dotenv::parse(file_get_contents($path)) : [];
$read = static function (string $key, string $default) use ($local): string {
    $value = getenv($key);
    return $value !== false ? $value : (string) ($local[$key] ?? $default);
};
if (is_file(dirname(__DIR__) . '/bootstrap/cache/config.php')) {
    throw new RuntimeException('Clear Laravel configuration cache before PostgreSQL testing.');
}
$forcedPgTestEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => $read('DB_HOST', '127.0.0.1'),
    'DB_PORT' => $read('DB_PORT', '5432'),
    'DB_DATABASE' => 'koperadata_test',
    'DB_USERNAME' => $read('DB_USERNAME', 'postgres'),
    'DB_PASSWORD' => $read('DB_PASSWORD', ''),
    'DB_URL' => '',
];
if (!in_array($forcedPgTestEnv['DB_HOST'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('PostgreSQL tests require a local isolated database.');
}
foreach ($forcedPgTestEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
