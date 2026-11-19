<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const VALID_ROLES = ['MEMBER', 'CASHIER', 'MANAGER'];

    public function __construct(private AuditService $audit) {}

    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required_without:email|string|max:150',
            'email' => 'required_without:username|string|max:150',
            'password' => 'required|string',
            'role' => 'nullable|string|in:MEMBER,CASHIER,MANAGER',
        ]);

        $identifier = trim((string) ($validated['email'] ?? $validated['username'] ?? ''));
        $password = (string) $validated['password'];
        $requestedRole = isset($validated['role']) ? strtoupper((string) $validated['role']) : null;

        $user = $this->findUserByIdentifier($identifier);

        if (!$user || !$user->isActive() || !Hash::check($password, (string) $user->password_hash)) {
            $this->audit->record(
                'LOGIN_FAILED',
                'User',
                $user?->id,
                null,
                ['identifier' => $identifier],
                $user?->id,
                null,
                $request->ip()
            );

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_CREDENTIALS',
                    'message' => 'Nomor anggota/email atau kata sandi tidak valid.',
                ], 401);
            }

            return back()
                ->withInput($request->only('username', 'email', 'role'))
                ->withErrors(['username' => 'Nomor anggota/email atau kata sandi tidak valid.']);
        }

        // Ambil role valid hanya dari tabel user_roles (tidak boleh ADMIN)
        $dbRoles = array_values(array_intersect($user->roleCodes(), self::VALID_ROLES));
        if (empty($dbRoles)) {
            return $this->denyAccess($request, 'Akun Anda belum memiliki peran akses yang valid.');
        }

        if ($requestedRole && !in_array($requestedRole, $dbRoles, true)) {
            $this->audit->record(
                'LOGIN_ROLE_DENIED',
                'User',
                (string) $user->id,
                null,
                ['requested_role' => $requestedRole, 'allowed_roles' => $dbRoles],
                (string) $user->id,
                $dbRoles[0],
                $request->ip()
            );

            return $this->denyAccess($request, "Akun Anda tidak memiliki hak akses untuk peran {$requestedRole}.");
        }

        $activeRole = $requestedRole ?? $dbRoles[0];

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $member = $user->member;
        $displayName = $member?->full_name
            ?? ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $user->email)[0]));

        session([
            'user' => [
                'id' => $member?->employee_number ?? (string) $user->id,
                'user_id' => (string) $user->id,
                'member_id' => $member?->id ? (string) $member->id : null,
                'name' => $displayName,
                'email' => $user->email,
            ],
            'active_role' => $activeRole,
            'active_role_user_id' => (string) $user->id,
            'available_roles' => $dbRoles,
        ]);

        $this->audit->record(
            'LOGIN_SUCCESS',
            'User',
            (string) $user->id,
            null,
            ['active_role' => $activeRole],
            (string) $user->id,
            $activeRole,
            $request->ip()
        );

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Berhasil masuk.',
                'user' => session('user'),
                'role' => $activeRole,
                'roles' => $dbRoles,
            ], 200);
        }

        $redirectUrl = $request->input('redirect_url');
        if (is_string($redirectUrl) && str_starts_with($redirectUrl, '/') && !str_starts_with($redirectUrl, '//') && !str_contains($redirectUrl, '\\')) {
            return redirect($redirectUrl)->with('success', 'Berhasil masuk.');
        }

        return match ($activeRole) {
            'CASHIER' => redirect()->route('cashier.pos')->with('success', 'Masuk ke Portal Kasir & Stok.'),
            'MANAGER' => redirect()->route('manager.dashboard')->with('success', 'Masuk ke Portal Analitik Manajer.'),
            default => redirect()->route('landing')->with('success', "Masuk sebagai Anggota Koperasi ({$displayName})."),
        };
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $this->audit->record('LOGOUT', 'User', (string) $user->id, null, null, (string) $user->id);
        }

        Auth::logout();
        if ($request->hasSession()) {
            $request->session()->forget(['user', 'active_role', 'active_role_user_id', 'available_roles']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'message' => 'Berhasil keluar.']);
        }

        return redirect()->route('landing')->with('info', 'Anda telah keluar dari akun.');
    }

    public function switchAccount(Request $request)
    {
        $this->logout($request);
        return redirect()->route('login')->with('info', 'Silakan masuk menggunakan akun lain.');
    }

    public function switchRole(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }
            return redirect()->route('login');
        }

        $requestedRole = strtoupper((string) $request->input('role', ''));
        $dbRoles = array_values(array_intersect($user->roleCodes(), self::VALID_ROLES));

        if (!in_array($requestedRole, $dbRoles, true)) {
            $this->audit->record(
                'SWITCH_ROLE_DENIED',
                'User',
                (string) $user->id,
                null,
                ['requested_role' => $requestedRole, 'allowed_roles' => $dbRoles]
            );

            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'FORBIDDEN',
                    'message' => 'Akses ditolak: Anda tidak memiliki peran tersebut.',
                ], 403);
            }

            return redirect()->route('landing')->with('error', 'Akses Ditolak: Anda tidak memiliki hak akses ke portal tersebut.');
        }

        $oldRole = session('active_role');
        session([
            'active_role' => $requestedRole,
            'active_role_user_id' => (string) $user->id,
            'available_roles' => $dbRoles,
        ]);

        $this->audit->record('SWITCH_ROLE', 'User', (string) $user->id, ['role' => $oldRole], ['role' => $requestedRole]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['success' => true, 'active_role' => $requestedRole, 'roles' => $dbRoles]);
        }

        return match ($requestedRole) {
            'CASHIER' => redirect()->route('cashier.pos')->with('info', 'Beralih ke Portal Kasir & Stok.'),
            'MANAGER' => redirect()->route('manager.dashboard')->with('info', 'Beralih ke Portal Analitik Manajer.'),
            default => redirect()->route('landing')->with('info', 'Beralih ke Etalase Anggota.'),
        };
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->roleCodes(),
                'active_role' => session('active_role') ?: ($user->roleCodes()[0] ?? null),
                'member' => $user->member ? [
                    'id' => $user->member->id,
                    'employee_number' => $user->member->employee_number,
                    'member_number' => $user->member->member_number,
                    'full_name' => $user->member->full_name,
                    'department' => $user->member->department,
                    'status' => $user->member->status,
                ] : null,
            ],
        ]);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($identifier)])->first();
        if ($user) {
            return $user;
        }

        $member = Member::whereRaw('UPPER(employee_number) = ?', [strtoupper($identifier)])
            ->orWhereRaw('UPPER(member_number) = ?', [strtoupper($identifier)])
            ->first();

        return $member?->user;
    }

    private function denyAccess(Request $request, string $message)
    {
        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => $message,
            ], 403);
        }

        return back()->withInput($request->only('username', 'email'))->withErrors(['username' => $message]);
    }
}
