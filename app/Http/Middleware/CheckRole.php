<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    private const VALID_ROLES = ['MEMBER', 'CASHIER', 'MANAGER'];

    /**
     * Handle an incoming request.
     * Otorisasi berbasis database (users & user_roles). Tidak menerima klaim role dari mock session.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  mixed ...$roles  Daftar role yang diizinkan (contoh: CASHIER, MANAGER)
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user() ?? Auth::user();

        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'UNAUTHORIZED',
                    'message' => 'Unauthorized. Silakan login terlebih dahulu.'
                ], 401);
            }

            return redirect()->route('login')->with('warning', 'Silakan masuk terlebih dahulu untuk mengakses portal ini.');
        }

        if (!$user->isActive()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message' => 'Akun pengguna tidak aktif.'
                ], 403);
            }

            return redirect()->route('landing')->with('error', 'Akun pengguna tidak aktif.');
        }

        $allowedRoles = array_values(array_intersect(
            array_map(fn ($r) => strtoupper(trim((string) $r)), $roles),
            self::VALID_ROLES
        ));

        $dbRoles = array_values(array_intersect($user->roleCodes(), self::VALID_ROLES));
        $normalizedActive = null;

        if ($request->hasSession()) {
            $sessionUserId = $request->session()->get('active_role_user_id') ?? $request->session()->get('user.user_id');
            if ($sessionUserId !== null && (string) $sessionUserId !== (string) $user->getAuthIdentifier()) {
                // Bersihkan active_role sisa akun lain (misal saat actingAs berganti user di sesi yang sama)
                $request->session()->forget(['active_role', 'active_role_user_id', 'available_roles', 'user']);
            }

            $activeRole = $request->session()->get('active_role');
            if ($activeRole !== null && trim((string) $activeRole) !== '') {
                $candidateRole = strtoupper(trim((string) $activeRole));
                if (!in_array($candidateRole, $dbRoles, true)) {
                    // Jangan pernah memercayai active_role yang tidak ada di DB user_roles
                    $request->session()->forget(['active_role', 'active_role_user_id']);
                    return $this->denyForbidden($request, $allowedRoles);
                }
                $normalizedActive = $candidateRole;
            }
        }

        if (empty($allowedRoles) || empty(array_intersect($dbRoles, $allowedRoles))) {
            return $this->denyForbidden($request, $allowedRoles);
        }

        if ($normalizedActive !== null && !in_array($normalizedActive, $allowedRoles, true)) {
            return $this->denyForbidden(
                $request,
                $allowedRoles,
                "Peran aktif saat ini ({$normalizedActive}) tidak memiliki izin untuk mengakses fitur ini."
            );
        }

        return $next($request);
    }

    private function denyForbidden(Request $request, array $allowedRoles, ?string $customMessage = null): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => $customMessage ?? 'Forbidden. Anda tidak memiliki hak akses (Role) untuk melakukan aksi ini.',
            ], 403);
        }

        $portalName = in_array('MANAGER', $allowedRoles, true) && !in_array('CASHIER', $allowedRoles, true)
            ? 'Manajer'
            : (in_array('CASHIER', $allowedRoles, true) ? 'Kasir' : 'ini');

        return redirect()->route('landing')->with(
            'error',
            $customMessage ?? "Akses Ditolak: Anda tidak memiliki hak akses ke portal {$portalName}."
        );
    }
}
