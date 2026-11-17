{{-- ==============================================================================
   footer.blade.php - Footer Resmi KoperasiKu (KOPEG POLMAN Bandung)
   - Informasi jam operasional gerai & kantor administrasi
   - Tautan navigasi etalase & layanan anggota
   ============================================================================== --}}
<footer class="landing-footer">
    <div class="container px-3 px-lg-4">
        <div class="row g-4 g-lg-5 mb-5">
            <!-- Brand & Tagline -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="bg-primary text-white rounded-2 p-1.5 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                        <i class="bi bi-intersect fs-6" aria-hidden="true"></i>
                    </div>
                    <span class="fs-5 fw-bold text-white">{{ config('koperasi.brand_name') }}</span>
                </div>
                <p class="small text-light text-opacity-80 mb-3 lh-base">
                    {{ config('koperasi.full_name') }}. Platform digital belanja sembako dan kebutuhan harian karyawan dengan sistem penyerahan langsung di gerai (Pickup Only).
                </p>
                <div class="small text-light text-opacity-70">
                    <i class="bi bi-geo-alt-fill me-1 text-primary" aria-hidden="true"></i> {{ config('koperasi.contact.address') }}
                </div>
            </div>

            <!-- Jam Pelayanan -->
            <div class="col-12 col-sm-6 col-lg-3">
                <h6 class="text-white fw-bold mb-3 fs-6">Jam Operasional</h6>
                <div class="mb-2">
                    <small class="text-white-50 d-block" style="font-size: 0.72rem;">Gerai KOPEG Mart:</small>
                    <span class="small text-light fw-medium">{{ config('koperasi.operating_hours.mart.days') }}</span>
                    <span class="d-block small text-light text-opacity-75">{{ config('koperasi.operating_hours.mart.hours') }}</span>
                </div>
                <div class="pt-2">
                    <small class="text-white-50 d-block" style="font-size: 0.72rem;">Kantor Administrasi:</small>
                    <span class="small text-light fw-medium">{{ config('koperasi.operating_hours.office.days') }}</span>
                    <span class="d-block small text-light text-opacity-75">{{ config('koperasi.operating_hours.office.hours') }}</span>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-12 col-sm-6 col-lg-2">
                <h6 class="text-white fw-bold mb-3 fs-6">Navigasi Cepat</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small mb-0">
                    <li><a href="#beranda">Beranda</a></li>
                    <li><a href="#best-deals">Best Deals</a></li>
                    <li><a href="#sembako-section">Sembako & Dapur</a></li>
                    <li><a href="#alur-pesan">Alur Cara Pesan</a></li>
                    <li><a href="#faq">FAQ & Bantuan</a></li>
                    <li><a href="#payment-methods">Metode Pembayaran</a></li>
                </ul>
            </div>

            <!-- Contact Channels -->
            <div class="col-12 col-md-6 col-lg-3">
                <h6 class="text-white fw-bold mb-3 fs-6">Hubungi Kami</h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small text-light text-opacity-80 mb-3">
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-telephone text-primary" aria-hidden="true"></i>
                        <span>{{ config('koperasi.contact.phone') }}</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-whatsapp text-success" aria-hidden="true"></i>
                        <a href="{{ config('koperasi.contact.whatsapp_url') }}" target="_blank" rel="noopener noreferrer" class="text-light text-opacity-80">
                            {{ config('koperasi.contact.whatsapp') }}
                        </a>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope text-primary" aria-hidden="true"></i>
                        <a href="mailto:{{ config('koperasi.contact.email') }}" class="text-light text-opacity-80">
                            {{ config('koperasi.contact.email') }}
                        </a>
                    </li>
                </ul>
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm w-100 fw-semibold py-2">
                    Masuk ke Sistem KoperasiKu
                </a>
            </div>
        </div>

        <div class="pt-4 border-top border-secondary border-opacity-25 d-flex flex-column flex-md-row justify-content-between align-items-center text-center text-md-start gap-2 small text-light text-opacity-70">
            <div>
                &copy; {{ date('Y') }} <span class="text-white fw-semibold">{{ config('koperasi.brand_name') }}</span> &bull; {{ config('koperasi.full_name') }}.
            </div>
            <div>
                Sistem Belanja Koperasi Konsumen Karyawan &bull; Pickup Gerai Kanayakan Dago.
            </div>
        </div>
    </div>
</footer>
