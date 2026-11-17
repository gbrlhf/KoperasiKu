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
        // Pastikan pengguna sudah login (menggunakan sanctum / auth:sanctum)
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Silakan login terlebih dahulu.'
            ], 401);
        }

        // Periksa apakah user memiliki salah satu dari role yang diizinkan
        // (Asumsi relasi role pada model User menggunakan 'roles' atau kolom 'role_code')
        $hasRole = false;

        // Jika Anda menggunakan pengecekan relasi role banyak (Many-to-Many / user_roles)
        if (method_exists($user, 'roles')) {
            $hasRole = $user->roles()->whereIn('code', $roles)->exists();
        } 
        // Atau jika menggunakan pengecekan properti tunggal pada user
        elseif (isset($user->role) && in_array($user->role, $roles)) {
            $hasRole = true;
        }

        if (!$hasRole) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Anda tidak memiliki hak akses (Role) untuk melakukan aksi ini.'
            ], 403);
        }

        return $next($request);
    }
}