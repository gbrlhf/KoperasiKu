<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * PIC D report access is intentionally fail-closed.
 * The frontend's mock active_role session is NOT an authorization claim.
 * PIC B must provide user_roles(user_id,role_code) and roles(code).
 */
class RequireManager
{
    public static function allowed(Request $request): bool
    {
        $user = $request->user();
        if (! $user || ! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            return false;
        }
        if (isset($user->status) && strtoupper($user->status) !== 'ACTIVE') {
            return false;
        }

        $hasManagerRole = DB::table('user_roles')
            ->join('roles', 'roles.code', '=', 'user_roles.role_code')
            ->where('user_roles.user_id', $user->getAuthIdentifier())
            ->where('roles.code', 'MANAGER')
            ->exists();

        if (! $hasManagerRole) {
            return false;
        }

        // Jika pengguna multi-role secara aktif beralih konteks ke MEMBER/CASHIER di sesi, cabut akses manajer sampai beralih kembali.
        if ($request->hasSession()) {
            $sessionUserId = $request->session()->get('active_role_user_id') ?? $request->session()->get('user.user_id');
            $belongsToCurrentUser = ($sessionUserId === null || (string) $sessionUserId === (string) $user->getAuthIdentifier());
            $activeRole = $request->session()->get('active_role');
            if ($belongsToCurrentUser && $activeRole !== null && trim((string) $activeRole) !== '' && strtoupper(trim((string) $activeRole)) !== 'MANAGER') {
                return false;
            }
        }

        return true;
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::allowed($request), 403, 'Laporan keuangan hanya dapat diakses Manajer terverifikasi.');

        return $next($request);
    }
}
