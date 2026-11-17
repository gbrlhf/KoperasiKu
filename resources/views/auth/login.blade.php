@extends('layouts.guest')

@section('title', 'Masuk Akun')

@section('content')
<div class="card shadow-sm border">
    <div class="card-body p-4 p-md-5">
        <!-- Logo & Header -->
        <div class="text-center mb-4">
            <div class="bg-primary text-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center mb-2 shadow-xs" style="width: 48px; height: 48px;">
                <i class="bi bi-intersect fs-4" aria-hidden="true"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1 fs-5">{{ config('koperasi.brand_name', 'KoperasiKu') }}</h4>
            <p class="text-secondary small mb-0">Masuk untuk mengakses layanan internal koperasi karyawan</p>
        </div>

        {{-- // TODO: integrasi langsung dengan auth API backend saat endpoint /api/v1/auth/login siap --}}
        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold small text-secondary">Nomor Anggota / ID Karyawan</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0 px-3"><i class="bi bi-person" aria-hidden="true"></i></span>
                    <input type="text" class="form-control border-start-0 ps-1" id="username" name="username" placeholder="Contoh: EMP-001" required value="EMP-001">
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label fw-semibold small text-secondary mb-0">Kata Sandi</label>
                    <a href="{{ config('koperasi.contact.whatsapp_url') }}" target="_blank" rel="noopener noreferrer" class="text-primary small text-decoration-none" style="font-size: 0.75rem;">
                        Lupa sandi?
                    </a>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0 px-3"><i class="bi bi-lock" aria-hidden="true"></i></span>
                    <input type="password" class="form-control border-start-0 ps-1" id="password" name="password" placeholder="••••••••" required value="password">
                </div>
            </div>

            <div class="mb-4">
                <label for="role" class="form-label fw-semibold small text-secondary mb-1">Pilih Peran Akses Awal</label>
                <select class="form-select" id="role" name="role">
                    <option value="MEMBER">Anggota Koperasi (MEMBER)</option>
                    <option value="CASHIER">Kasir & Petugas Gerai (CASHIER)</option>
                    <option value="MANAGER">Manajer Koperasi (MANAGER)</option>
                </select>
                <div class="form-text small text-muted mt-1" style="font-size: 0.72rem;">Peran dapat dialihkan kapan saja via pemilih konteks di navbar atas.</div>
            </div>

            <!-- Demo Quick Presets with generous spacing & clean role badges -->
            <div class="p-3 rounded-3 bg-light border mb-4 shadow-xs">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="text-secondary fw-bold text-uppercase d-inline-flex align-items-center gap-1.5" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                        <i class="bi bi-person-gear text-primary"></i>
                        <span>Pilih Akun Cepat Pengujian:</span>
                    </small>
                    <span class="badge bg-secondary-subtle text-secondary small" style="font-size: 0.65rem;">Klik untuk Isi</span>
                </div>
                <div class="d-grid d-sm-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm flex-fill py-1.5 px-2.5 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" onclick="setRole('EMP-001', 'MEMBER')">
                        <i class="bi bi-person-fill" aria-hidden="true"></i>
                        <span class="fw-semibold">Anggota</span>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm flex-fill py-1.5 px-2.5 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" onclick="setRole('CSH-001', 'CASHIER')">
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        <span class="fw-semibold">Kasir</span>
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm flex-fill py-1.5 px-2.5 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" onclick="setRole('MGR-001', 'MANAGER')">
                        <i class="bi bi-briefcase-fill" aria-hidden="true"></i>
                        <span class="fw-semibold">Manajer</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm d-inline-flex align-items-center justify-content-center">
                <i class="bi bi-box-arrow-in-right me-2 fs-5" aria-hidden="true"></i>
                <span>Masuk ke Sistem</span>
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <a href="{{ url('/') }}" class="text-decoration-none text-secondary small d-inline-flex align-items-center">
                <i class="bi bi-arrow-left me-1.5" aria-hidden="true"></i>
                <span>Kembali ke Etalase Toko</span>
            </a>
        </div>
    </div>
</div>

<script>
function setRole(username, role) {
    document.getElementById('username').value = username;
    document.getElementById('role').value = role;
}
</script>
@endsection
