@extends('layouts.guest')

@section('title', 'Masuk Akun')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="bg-primary text-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center mb-2" style="width: 48px; height: 48px;">
                <i class="bi bi-intersect fs-4"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">KOPERADATA</h4>
            <p class="text-muted small mb-0">Masuk untuk mengakses layanan koperasi karyawan</p>
        </div>

        {{-- // TODO: ganti dengan auth API --}}
        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            
            <div class="mb-3">
                <label for="username" class="form-label fw-medium small text-secondary">Nomor Anggota / ID Karyawan</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0" id="username" name="username" placeholder="Contoh: EMP-001" required value="EMP-001">
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fw-medium small text-secondary">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="••••••••" required value="password">
                </div>
            </div>

            <div class="mb-4">
                <label for="role" class="form-label fw-medium small text-secondary">Pilih Peran Awal (Mockup)</label>
                <select class="form-select" id="role" name="role">
                    <option value="MEMBER">Anggota (MEMBER)</option>
                    <option value="CASHIER">Kasir / Petugas (CASHIER)</option>
                    <option value="MANAGER">Manajer (MANAGER)</option>
                </select>
                <div class="form-text small">Konteks peran dapat dialihkan kapan saja di dalam sistem.</div>
            </div>

            <x-button-submit variant="primary" class="w-100 py-2.5 fw-semibold" icon="bi-box-arrow-in-right">
                Masuk ke Sistem
            </x-button-submit>
        </form>
    </div>
</div>
@endsection
