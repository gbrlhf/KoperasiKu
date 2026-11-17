{{-- ==============================================================================
   hero.blade.php - Asymmetric Split Hero Banner (Inspirasi e-mox Layout)
   - Left Banner (~65%): Banner Utama Promo Sembako Karyawan
   - Right Banner (~35%): Banner Fasilitas Piutang Anggota Tempo 7 Hari Bunga 0%
   ============================================================================== --}}
<section id="beranda" class="hero-split-grid py-3">
    <div class="container px-3 px-lg-4">
        <div class="row g-3 g-lg-4 align-items-stretch">
            
            <!-- Left Banner: Main Sembako Promo Banner (~65% width) -->
            <div class="col-12 col-lg-8">
                <div class="hero-main-banner">
                    <div class="position-relative z-1" style="max-width: 580px;">
                        
                        <!-- Top Badges -->
                        <div class="d-inline-flex align-items-center gap-2 mb-3 flex-wrap">
                            <span class="badge bg-warning text-dark px-3 py-1.5 fw-bold rounded-pill" style="font-size: 0.75rem; letter-spacing: 0.04em;">
                                <i class="bi bi-lightning-charge-fill me-1"></i> HARGA KHUSUS ANGGOTA
                            </span>
                            <span class="hero-glass-pill">
                                <i class="bi bi-geo-alt-fill me-1"></i> Pickup Only Kanayakan
                            </span>
                        </div>

                        <h1 class="display-6 fw-extrabold text-white mb-2.5 lh-sm tracking-tight">
                            Belanja Sembako Keluarga <br class="d-none d-sm-inline">
                            Hemat, Lengkap & Tanpa Antre
                        </h1>

                        <p class="text-white text-opacity-90 fs-6 mb-4 lh-base">
                            Pesan beras ramos, minyak goreng, gula kristal, dan susu keluarga dari gawai Anda. Ambil langsung di Gerai KOPEG Mart saat jam operasional.
                        </p>

                        <!-- Feature Chips (Translucent Glass Badges with Bright Text) -->
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-4">
                            <span class="hero-glass-pill">
                                <i class="bi bi-check-circle-fill text-warning me-1.5"></i>
                                <span>Stok Terverifikasi</span>
                            </span>
                            <span class="hero-glass-pill">
                                <i class="bi bi-clock-history text-warning me-1.5"></i>
                                <span>Siap Diambil di Gerai</span>
                            </span>
                            <span class="hero-glass-pill">
                                <i class="bi bi-shield-check text-warning me-1.5"></i>
                                <span>Bebas Biaya Ongkir</span>
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-2.5">
                            <a href="#best-deals" class="btn btn-warning text-dark px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center">
                                <i class="bi bi-cart-fill me-2"></i>
                                <span>Mulai Belanja</span>
                            </a>
                            <a href="#alur-pesan" class="btn btn-outline-light px-4 py-2.5 fw-semibold d-inline-flex align-items-center">
                                <i class="bi bi-info-circle me-2"></i>
                                <span>Cara Pemesanan</span>
                            </a>
                        </div>
                    </div>

                    <!-- Right Graphic Accents Inside Hero Main Banner -->
                    <div class="position-absolute end-0 bottom-0 p-4 d-none d-md-block opacity-25 text-white pointer-events-none" style="transform: rotate(-10deg) translate(20px, 20px);">
                        <i class="bi bi-bag-heart-fill" style="font-size: 14rem; line-height: 1;"></i>
                    </div>
                </div>
            </div>

            <!-- Right Banner: Piutang Anggota Tempo 7 Hari (~35% width) -->
            <div class="col-12 col-lg-4">
                <div class="hero-side-banner">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-white text-success fw-bold px-3 py-1.5 rounded-pill" style="font-size: 0.72rem;">
                            <i class="bi bi-star-fill text-warning me-1"></i> FASILITAS ANGGOTA
                        </span>
                        <small class="text-white-50">KOPEG POLMAN</small>
                    </div>

                    <h2 class="fw-bold text-white mb-2 fs-4 lh-sm">
                        Beli Hari Ini, <br>
                        Bayar Tempo 7 Hari
                    </h2>

                    <p class="small text-white text-opacity-90 mb-4 lh-base">
                        Gunakan saldo fasilitas piutang belanja konsumsi. Tanpa bunga, jatuh tempo tepat <strong>7 hari kalender</strong> setelah barang diambil di kasir.
                    </p>

                    <!-- Real Value 3-Col Box -->
                    <div class="p-3 rounded-2 bg-white bg-opacity-10 border border-white border-opacity-15 mb-3">
                        <div class="row g-1 text-center">
                            <div class="col-4 border-end border-white border-opacity-15">
                                <div class="fw-bold text-white fs-6">0%</div>
                                <div class="text-white-50" style="font-size: 0.7rem;">Bunga Konsumsi</div>
                            </div>
                            <div class="col-4 border-end border-white border-opacity-15">
                                <div class="fw-bold text-white fs-6">7 Hari</div>
                                <div class="text-white-50" style="font-size: 0.7rem;">Jatuh Tempo</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-white fs-6">Pickup</div>
                                <div class="text-white-50" style="font-size: 0.7rem;">Gerai Dago</div>
                            </div>
                        </div>
                    </div>

                    <!-- Member Limit & Tagihan Shortcut Button -->
                    <button type="button" class="btn btn-warning text-dark btn-sm w-100 fw-bold py-2 mb-3 shadow-xs d-inline-flex align-items-center justify-content-center gap-1.5" data-bs-toggle="offcanvas" data-bs-target="#memberProfileDrawer">
                        <i class="bi bi-wallet2"></i>
                        <span>Cek Limit & Tagihan Saya</span>
                        @if(!empty($receivableStatus['has_debt']))
                            <span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem;">Rp {{ number_format($receivableStatus['total_debt'], 0, ',', '.') }}</span>
                        @endif
                    </button>

                    <div class="mt-auto d-flex align-items-center justify-content-between text-white-50 small pt-1">
                        <span style="font-size: 0.78rem;">
                            <i class="bi bi-shop me-1 text-warning"></i> Gerai Kanayakan A11
                        </span>
                        <a href="#faq" class="text-white text-decoration-none fw-semibold d-inline-flex align-items-center">
                            <span class="me-1" style="font-size: 0.8rem;">Syarat & Ketentuan</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
