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

        @if($errors->any())
            <div class="alert alert-danger py-2 px-3 small mb-3">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold small text-secondary">Nomor Anggota / ID Karyawan / Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0 px-3"><i class="bi bi-person" aria-hidden="true"></i></span>
                    <input type="text" class="form-control border-start-0 ps-1" id="username" name="username" placeholder="Contoh: EMP-001" required autocomplete="username" value="{{ old('username', old('email', '')) }}">
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
                    <input type="password" class="form-control border-start-0 ps-1" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" value="">
                </div>
            </div>

            <div class="mb-4">
                <label for="role" class="form-label fw-semibold small text-secondary mb-1">Pilih Peran Akses Awal</label>
                <select class="form-select" id="role" name="role">
                    <option value="MEMBER" @selected(old('role', 'MEMBER') === 'MEMBER')>Anggota Koperasi (MEMBER)</option>
                    <option value="CASHIER" @selected(old('role', 'MEMBER') === 'CASHIER')>Kasir & Petugas Gerai (CASHIER)</option>
                    <option value="MANAGER" @selected(old('role', 'MEMBER') === 'MANAGER')>Manajer Koperasi (MANAGER)</option>
                </select>
                <div class="form-text small text-muted mt-1" style="font-size: 0.72rem;">Peran dapat dialihkan kapan saja via pemilih konteks di navbar atas.</div>
            </div>

            <!-- Kotak Akun Terakhir yang Pernah Login di Perangkat Ini (Hanya Muncul Jika Pernah Login) -->
            <div id="rememberedAccountBox" class="p-3 rounded-3 bg-light border mb-4 shadow-xs d-none">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="text-secondary fw-bold text-uppercase d-inline-flex align-items-center gap-1.5" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                        <i class="bi bi-clock-history text-primary"></i>
                        <span>Akun Terakhir di Perangkat Ini:</span>
                    </small>
                    <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none" style="font-size: 0.68rem;" onclick="forgetLoginAccount()">
                        Hapus
                    </button>
                </div>
                <div class="d-flex align-items-center justify-content-between p-2 rounded-2 bg-white border">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                            AF
                        </div>
                        <div class="lh-sm">
                            <div class="fw-bold text-dark small" id="loginRememberedName">Ahmad Fauzi</div>
                            <small class="text-muted" style="font-size: 0.7rem;" id="loginRememberedId">EMP-001</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm px-2.5 py-1 fw-semibold" onclick="applyRememberedAccount()">
                        Gunakan
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
document.addEventListener('DOMContentLoaded', () => {
    try {
        const saved = localStorage.getItem('koperasiku_verified_member_v2');
        if (saved) {
            const member = JSON.parse(saved);
            const box = document.getElementById('rememberedAccountBox');
            const nameEl = document.getElementById('loginRememberedName');
            const idEl = document.getElementById('loginRememberedId');
            if (box && nameEl && idEl && member.name && member.id) {
                nameEl.textContent = member.name;
                idEl.textContent = member.id + ' • Pernah Masuk';
                box.classList.remove('d-none');
            }
        }
    } catch (e) {}
});

function applyRememberedAccount() {
    try {
        const saved = localStorage.getItem('koperasiku_verified_member_v2');
        if (saved) {
            const member = JSON.parse(saved);
            if (!member.id) return;
            document.getElementById('username').value = member.id;
            document.getElementById('password').value = '';
            document.getElementById('role').value = 'MEMBER';
            document.getElementById('password')?.focus();
        }
    } catch (e) {}
}

function forgetLoginAccount() {
    try {
        localStorage.removeItem('koperasiku_verified_member_v2');
        const box = document.getElementById('rememberedAccountBox');
        if (box) box.classList.add('d-none');
    } catch (e) {}
}

</script>
@endsection
