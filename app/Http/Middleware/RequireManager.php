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

        return DB::table('user_roles')
            ->join('roles', 'roles.code', '=', 'user_roles.role_code')
            ->where('user_roles.user_id', $user->getAuthIdentifier())
            ->where('roles.code', 'MANAGER')
            ->exists();
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::allowed($request), 403, 'Laporan keuangan hanya dapat diakses Manajer terverifikasi.');

        return $next($request);
    }
}
