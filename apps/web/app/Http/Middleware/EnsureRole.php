<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->role;

        if (!$userRole && app()->environment('local')) {
            $userRole = strtoupper((string) $request->header('X-Mock-Role', ''));
        }

        if (!in_array($userRole, $roles, true)) {
            return response()->json([
                'message' => 'Akses ditolak. Role tidak memiliki izin untuk aksi ini.',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}