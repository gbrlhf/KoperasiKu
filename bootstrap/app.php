<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\RequireManager;
use App\Http\Middleware\VerifyApiCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Bagikan sesi autentikasi web secara aman beserta validasi CSRF pada rute stateful /api/*
        $middleware->prependToGroup('api', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            VerifyApiCsrfToken::class,
        ]);

        // Pastikan request ke /api/* yang belum login tidak dialihkan ke HTML /login
        $middleware->redirectGuestsTo(fn ($request) => ($request->is('api/*') || $request->expectsJson()) ? null : route('login'));

        $middleware->alias([
            'role' => CheckRole::class,
            'manager' => RequireManager::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn ($request, \Throwable $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(function (TokenMismatchException|HttpException $e, $request) {
            $isCsrfMismatch = $e instanceof TokenMismatchException || $e->getStatusCode() === 419;
            if ($isCsrfMismatch && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'CSRF_TOKEN_MISMATCH',
                    'message' => 'Token CSRF tidak valid atau kedaluwarsa. Muat ulang halaman dan coba lagi.',
                ], 419);
            }

            return null;
        });
    })->create();
