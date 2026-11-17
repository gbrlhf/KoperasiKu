{{-- ==============================================================================
   login-required-modal.blade.php - Modal Masuk Akun Anggota
   - Pengguna Baru / Belum Pernah Login: Harus memasukkan Nomor Anggota & Kata Sandi.
   - Pengguna yang Pernah Login Sebelumnya: Menampilkan opsi "Masuk Cepat Kembali".
   ============================================================================== --}}

<div class="modal fade" id="loginRequiredModal" tabindex="-1" aria-labelledby="loginRequiredModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            
            <div class="modal-header border-bottom py-2.5 px-3 px-md-4 bg-light align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-shield-lock-fill fs-6" aria-hidden="true"></i>
                    </div>
                    <h6 class="modal-title fw-bold text-dark mb-0 fs-6" id="loginRequiredModalLabel">
                        Masuk Akun Anggota
                    </h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup jendela"></button>
            </div>

            <div class="modal-body p-4 text-center">
                <!-- Icon Header -->
                <div class="d-inline-flex p-3 rounded-circle bg-primary-subtle text-primary mb-3 shadow-xs" style="width: 64px; height: 64px; align-items: center; justify-content: center;">
                    <i class="bi bi-bag-check-fill" style="font-size: 1.8rem;"></i>
                </div>

                <h5 class="fw-bold text-dark mb-1 fs-5">Silakan Masuk Terlebih Dahulu</h5>
                
                <p class="text-secondary small mb-3 lh-base">
                    Untuk memasukkan <span id="loginRequiredProductName" class="fw-bold text-primary">produk</span> ke keranjang dan memesan, silakan masuk ke akun anggota Anda.
                </p>

                <!-- 1. KOTAK MASUK CEPAT (HANYA MUNCUL JIKA SUDAH PERNAH LOGIN DI PERANGKAT INI) -->
                <div id="modalRememberedUserBox" class="p-3 rounded-3 bg-light border mb-3 text-start d-none shadow-xs">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-secondary fw-bold text-uppercase d-inline-flex align-items-center gap-1" style="font-size: 0.7rem; letter-spacing: 0.04em;">
                            <i class="bi bi-clock-history text-primary"></i>
                            <span>Pernah Masuk di Perangkat Ini:</span>
                        </small>
                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">Tersimpan</span>
                    </div>

                    <div class="d-flex align-items-center gap-2.5 p-2 rounded-2 bg-white border mb-3">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem;" id="rememberedMemberAvatar">
                            AF
                        </div>
                        <div class="lh-sm text-truncate flex-grow-1">
                            <div class="fw-bold text-dark small text-truncate" id="rememberedMemberName">Ahmad Fauzi</div>
                            <small class="text-muted" style="font-size: 0.72rem;" id="rememberedMemberId">EMP-001 &bull; Anggota</small>
                        </div>
                    </div>

                    <button type="button" id="btnQuickLoginRemembered" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-xs mb-2" onclick="quickLoginAsRememberedMember()">
                        <i class="bi bi-lightning-charge-fill text-warning"></i>
                        <span>Masuk Cepat sebagai <span id="btnRememberedMemberName">Ahmad Fauzi</span></span>
                    </button>

                    <div class="d-flex justify-content-between align-items-center pt-1 border-top">
                        <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none fw-semibold" style="font-size: 0.75rem;" onclick="showOtherLoginForm()">
                            <i class="bi bi-person-plus me-1"></i> Masuk dengan Akun Lain
                        </button>
                        <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none" style="font-size: 0.72rem;" onclick="forgetRememberedMember()">
                            Lupakan
                        </button>
                    </div>
                </div>

                <!-- 2. FORM LOGIN BIASA (DEFAULT UNTUK YANG BELUM PERNAH LOGIN) -->
                <form id="modalLoginForm" onsubmit="handleModalLoginFormSubmit(event)" class="text-start">
                    <div class="mb-2.5">
                        <label for="modalLoginUsername" class="form-label small fw-bold text-secondary mb-1">
                            Nomor Anggota / ID Karyawan
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted px-2.5"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="modalLoginUsername" name="username" placeholder="Contoh: EMP-001" required autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="modalLoginPassword" class="form-label small fw-bold text-secondary mb-0">
                                Kata Sandi
                            </label>
                            <a href="{{ config('koperasi.contact.whatsapp_url') }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none" style="font-size: 0.72rem;">
                                Lupa sandi?
                            </a>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted px-2.5"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="modalLoginPassword" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                        </div>
                    </div>

                    <div id="modalLoginAlertError" class="alert alert-danger py-1.5 px-2.5 small d-none mb-3" style="font-size: 0.74rem;"></div>

                    <button type="submit" id="btnModalLoginSubmit" class="btn btn-primary w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-xs mb-2">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Masuk & Lanjutkan Belanja</span>
                    </button>

                    <div class="text-center pt-2 border-top">
                        <a href="{{ route('login') }}" class="text-decoration-none text-secondary small d-inline-flex align-items-center gap-1" style="font-size: 0.76rem;">
                            <i class="bi bi-box-arrow-up-right"></i>
                            <span>Buka Halaman Login Penuh</span>
                        </a>
                    </div>
                </form>

            </div>

        </div>
    </div>
</div>
