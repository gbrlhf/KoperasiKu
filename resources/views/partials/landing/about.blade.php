<section id="tentang-kami" class="py-5 bg-white border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="row align-items-center g-4 g-lg-5">
            <!-- Left Column: About Profile -->
            <div class="col-12 col-lg-7">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fw-semibold small mb-2">
                    Profil Koperasi
                </span>
                <h2 class="fw-bold text-dark mb-3 fs-3">Koperasi Konsumen Pegawai POLMAN Bandung</h2>
                
                <p class="text-secondary small mb-3 lh-base">
                    <strong>{{ config('koperasi.full_name') }}</strong> merupakan lembaga ekonomi bersama civitas akademika dan karyawan yang berfokus pada penyediaan barang konsumsi bermutu dan terjangkau. Melalui platform digital <strong>{{ config('koperasi.brand_name') }}</strong>, pemesanan sembako dan pencatatan piutang terkelola secara otomatis dan transparan.
                </p>

                <p class="text-secondary small mb-4 lh-base">
                    Berdiri dengan prinsip keterbukaan dan keadilan ekonomi kerakyatan, seluruh perputaran usaha didedikasikan untuk peningkatan kesejahteraan anggota. Gerai fisik KOPEG Mart kini bertempat di gedung baru tepat di depan kampus POLMAN Bandung.
                </p>

                <!-- Value Pillars -->
                <div class="row g-3">
                    <div class="col-12 col-sm-4">
                        <div class="p-3 rounded-2 bg-light border h-100">
                            <i class="bi bi-shield-check text-primary fs-4 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1 small">Transparan</h6>
                            <p class="text-secondary mb-0" style="font-size: 0.78rem;">Pencatatan saldo piutang dan transaksi tersinkronisasi rapi dengan sistem kasir.</p>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="p-3 rounded-2 bg-light border h-100">
                            <i class="bi bi-people text-primary fs-4 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1 small">Milik Bersama</h6>
                            <p class="text-secondary mb-0" style="font-size: 0.78rem;">Dikelola oleh anggota dan untuk anggota dengan pengawasan dewan pengurus berkala.</p>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="p-3 rounded-2 bg-light border h-100">
                            <i class="bi bi-lightning-charge text-primary fs-4 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark mb-1 small">Pelayanan Cepat</h6>
                            <p class="text-secondary mb-0" style="font-size: 0.78rem;">Pesan dari meja kerja, tunjukkan kode pickup di kasir, dan bawa pulang belanjaan.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Location & Hours Card -->
            <div class="col-12 col-lg-5">
                <div class="card shadow-sm border p-4 bg-white">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <div class="bg-primary text-white rounded-2 p-1.5 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-geo-alt-fill fs-6"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-0 fs-6">Gerai & Jam Operasional</h5>
                    </div>

                    <!-- Address Information -->
                    <div class="mb-3 pb-3 border-bottom">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Lokasi Fisik Gerai:</small>
                        <p class="text-dark small mb-1 fw-medium">{{ config('koperasi.contact.address') }}</p>
                        <span class="badge bg-light text-primary border small font-monospace">
                            <i class="bi bi-building me-1"></i>{{ config('koperasi.contact.location_note') }}
                        </span>
                    </div>

                    <!-- Schedule -->
                    <div class="mb-4">
                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: 0.7rem;">Jadwal Buka Pelayanan:</small>
                        <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom small">
                            <span class="text-secondary fw-medium">KOPEG Mart (Belanja)</span>
                            <span class="fw-bold text-dark">{{ config('koperasi.operating_hours.mart.days') }}: {{ config('koperasi.operating_hours.mart.hours') }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1.5 border-bottom small">
                            <span class="text-secondary fw-medium">Kantor Koperasi</span>
                            <span class="fw-bold text-dark">{{ config('koperasi.operating_hours.office.days') }}: {{ config('koperasi.operating_hours.office.hours') }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-1.5 small text-muted">
                            <span>Hari Minggu & Libur</span>
                            <span class="badge bg-danger-subtle text-danger fw-semibold">Tutup</span>
                        </div>
                    </div>

                    <!-- Contact Actions -->
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ config('koperasi.contact.whatsapp_url') }}" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-whatsapp"></i>
                            <span>Chat WhatsApp ({{ config('koperasi.contact.whatsapp') }})</span>
                        </a>
                        <a href="mailto:{{ config('koperasi.contact.email') }}" class="btn btn-outline-secondary btn-sm py-2 fw-medium d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-envelope"></i>
                            <span>{{ config('koperasi.contact.email') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
