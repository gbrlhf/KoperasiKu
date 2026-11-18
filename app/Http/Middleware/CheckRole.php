<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  mixed ...$roles  Daftar role yang diizinkan (contoh: CASHIER, MANAGER)
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Cek sesi web aktif (Session-based RBAC)
        $sessionRole = session('active_role');
        $sessionUser = session('user');

        if ($sessionUser && $sessionRole) {
            if (!in_array($sessionRole, $roles)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Forbidden. Pengguna (MEMBER/Pembeli) tidak memiliki akses ke portal ini.'
                    ], 403);
                }

                $portalName = in_array('MANAGER', $roles) ? 'Manajer' : (in_array('CASHIER', $roles) ? 'Kasir' : 'ini');
                return redirect()->route('landing')->with('error', "Akses Ditolak: Anda masuk sebagai Anggota/Pembeli dan tidak memiliki hak akses ke portal {$portalName}.");
            }

            return $next($request);
        }

        // 2. Cek autentikasi token / Sanctum ($request->user())
        $user = $request->user();

        if ($user) {
            $hasRole = false;

            if (method_exists($user, 'roles')) {
                $hasRole = $user->roles()->whereIn('code', $roles)->exists();
            } elseif (isset($user->role) && in_array($user->role, $roles)) {
                $hasRole = true;
            }

            if (!$hasRole) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Forbidden. Anda tidak memiliki hak akses (Role) untuk melakukan aksi ini.'
                    ], 403);
                }

                return redirect()->route('landing')->with('error', 'Akses Ditolak: Anda tidak memiliki izin untuk halaman ini.');
            }

            return $next($request);
        }

        // 3. Jika belum login sama sekali
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Silakan login terlebih dahulu.'
            ], 401);
        }

        return redirect()->route('login')->with('warning', 'Silakan masuk terlebih dahulu untuk mengakses portal ini.');
    }
}