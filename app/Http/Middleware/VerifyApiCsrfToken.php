<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * Perlindungan CSRF untuk rute /api/* yang memakai autentikasi cookie-session.
 * Tidak menonaktifkan CSRF secara global; menyediakan toggle eksplisit khusus pengujian middleware CSRF.
 */
class VerifyApiCsrfToken extends ValidateCsrfToken
{
    public static bool $enforceInTests = false;

    protected function runningUnitTests(): bool
    {
        if (static::$enforceInTests) {
            return false;
        }

        return parent::runningUnitTests();
    }
}
