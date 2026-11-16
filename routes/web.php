<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - KOPERADATA (PIC A - Frontend & UI/UX)
|--------------------------------------------------------------------------
| Berisi route antarmuka Blade untuk 3 role: MEMBER, CASHIER, dan MANAGER.
| Integrasi backend sesungguhnya dilakukan lewat REST API internal /api/v1.
*/

// Redirect root ke halaman sesuai active role
Route::get('/', function () {
    $role = session('active_role', 'MEMBER');

    return match ($role) {
        'CASHIER' => redirect()->route('cashier.antrian'),
        'MANAGER' => redirect()->route('manager.dashboard'),
        default => redirect()->route('member.katalog'),
    };
});

// --------------------------------------------------------------------------
// 1. Umum & Autentikasi (Mockup Sementara)
// --------------------------------------------------------------------------
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// // TODO: ganti dengan auth API
Route::post('/login', function (Request $request) {
    $role = $request->input('role', 'MEMBER');

    session([
        'user' => [
            'id' => 'EMP-001',
            'name' => 'Karyawan Demo',
            'username' => $request->input('username', 'EMP-001'),
        ],
        'active_role' => $role,
        'available_roles' => ['MEMBER', 'CASHIER', 'MANAGER'], // Satu akun punya multi-peran
    ]);

    return match ($role) {
        'CASHIER' => redirect()->route('cashier.antrian')->with('success', 'Berhasil masuk sebagai Kasir/Petugas.'),
        'MANAGER' => redirect()->route('manager.dashboard')->with('success', 'Berhasil masuk sebagai Manajer.'),
        default => redirect()->route('member.katalog')->with('success', 'Berhasil masuk sebagai Anggota.'),
    };
})->name('login.submit');

// Switch Role Context (Mockup Sementara untuk mempermudah review UI 3 Role)
// // TODO: ganti dengan auth API
Route::post('/mock-switch-role', function (Request $request) {
    $role = $request->input('role', 'MEMBER');
    $validRoles = ['MEMBER', 'CASHIER', 'MANAGER'];

    if (in_array($role, $validRoles)) {
        session(['active_role' => $role]);

        return match ($role) {
            'CASHIER' => redirect()->route('cashier.antrian')->with('info', 'Beralih ke konteks Kasir / Petugas.'),
            'MANAGER' => redirect()->route('manager.dashboard')->with('info', 'Beralih ke konteks Manajer.'),
            default => redirect()->route('member.katalog')->with('info', 'Beralih ke konteks Anggota.'),
        };
    }

    return back()->with('error', 'Peran tidak valid.');
})->name('mock.switch-role');

// --------------------------------------------------------------------------
// 2. Modul Anggota (MEMBER)
// --------------------------------------------------------------------------
Route::prefix('member')->name('member.')->group(function () {
    Route::get('/katalog', function () {
        return view('member.katalog');
    })->name('katalog');

    Route::get('/keranjang', function () {
        return view('member.keranjang');
    })->name('keranjang');

    Route::get('/pesanan', function () {
        return view('member.pesanan');
    })->name('pesanan');

    Route::get('/kredit', function () {
        return view('member.kredit');
    })->name('kredit');
});

// --------------------------------------------------------------------------
// 3. Modul Kasir / Petugas (CASHIER)
// --------------------------------------------------------------------------
Route::prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/antrian', function () {
        return view('cashier.antrian');
    })->name('antrian');

    Route::get('/pos', function () {
        return view('cashier.pos');
    })->name('pos');

    Route::get('/piutang', function () {
        return view('cashier.piutang');
    })->name('piutang');
});

// --------------------------------------------------------------------------
// 4. Modul Manajer (MANAGER)
// --------------------------------------------------------------------------
Route::prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', function () {
        return view('manager.dashboard');
    })->name('dashboard');

    Route::get('/kredit', function () {
        return view('manager.kredit');
    })->name('kredit');

    Route::get('/piutang', function () {
        return view('manager.piutang');
    })->name('piutang');
});
