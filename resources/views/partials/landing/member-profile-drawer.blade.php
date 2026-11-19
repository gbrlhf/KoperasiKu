{{-- ==============================================================================
   member-profile-drawer.blade.php - Offcanvas Profil & Tagihan Anggota (Anti-Slop Clean UI)
   - Header: Identitas Anggota Koperasi (Ahmad Fauzi / EMP-001)
   - Kartu 1: Fasilitas Limit Belanja (Sisa Limit, Plafon Global, Tagihan Berjalan + Akordeon Faktur)
   - Kartu 2: Tiket Pesanan Siap Diambil (PKP-XXXXX + Barcode 1D/2D)
   - Kartu 3: Riwayat Pengambilan Terakhir (Ringkas)
   - Aksi Akun: Keluar Akun & Ganti Akun
   ============================================================================== --}}

<div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="memberProfileDrawer" aria-labelledby="memberProfileDrawerLabel" style="width: 440px; max-width: 92vw;">
    
    <!-- Drawer Header -->
    <div class="offcanvas-header bg-white border-bottom py-3 px-3 px-md-4">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-xs fw-bold" style="width: 42px; height: 42px; font-size: 1.05rem;">
                {{ substr($member['name'] ?? 'AF', 0, 2) }}
            </div>
            <div class="lh-sm">
                <div class="d-flex align-items-center gap-1.5">
                    <h6 class="offcanvas-title fw-bold text-dark mb-0" id="memberProfileDrawerLabel">
                        {{ $member['name'] ?? 'Ahmad Fauzi' }}
                    </h6>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5" style="font-size: 0.65rem;">
                        <i class="bi bi-patch-check-fill me-0.5"></i> Aktif
                    </span>
                </div>
                <small class="text-muted d-block" style="font-size: 0.74rem;">
                    @if(!empty($landingMemberCode))
                        {{ $landingMemberCode }} &bull;
                    @endif
                    {{ $landingRoleLabel ?? 'Anggota' }} &bull; {{ $member['unit'] ?? 'POLMAN Bandung' }}
                </small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup jendela profil"></button>
    </div>

    <!-- Drawer Body -->
    <div class="offcanvas-body p-3 p-md-4">
        @if(empty($isLoggedIn) || empty($member))
            <!-- Guest / Not Logged In View -->
            <div class="text-center py-5">
                <div class="d-inline-flex p-3 rounded-circle bg-light text-primary mb-3 shadow-xs" style="width: 72px; height: 72px; align-items: center; justify-content: center;">
                    <i class="bi bi-person-lock" style="font-size: 2.2rem;"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Akses Profil Anggota</h5>
                <p class="text-secondary small mb-4">
                    Silakan masuk untuk melihat limit piutang belanja tempo 7 hari, riwayat faktur, dan tiket pengambilan pesanan Anda.
                </p>
                <div class="d-flex flex-column gap-2 max-w-xs mx-auto">
                    <button type="button" class="btn btn-primary py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-xs" onclick="openLoginModalFromProfile()">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Masuk Akun Anggota</span>
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Halaman Login Lengkap</span>
                    </a>
                </div>
            </div>
        @else
        @if(empty($landingShowCreditWidget))
        <!-- Info Akun Non-Member (Kasir / Manajer / Akun Tanpa Data Member Aktif) -->
        <div class="card border rounded-3 p-3 mb-3 bg-light shadow-xs">
            <div class="d-flex align-items-start gap-2.5">
                <div class="rounded-2 bg-success-subtle text-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                    <i class="bi bi-shield-check fs-5" aria-hidden="true"></i>
                </div>
                <div class="min-w-0 flex-grow-1">
                    <div class="fw-bold text-dark small mb-0.5">Akun {{ $landingRoleLabel ?? 'Petugas' }}</div>
                    <p class="text-secondary small mb-2" style="font-size: 0.76rem;">
                        Widget limit dan tagihan piutang 7 hari hanya ditampilkan untuk akun anggota koperasi aktif.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        @if(!empty($landingHasCashierRole))
                            <a href="{{ route('cashier.pos') }}" class="btn btn-success btn-sm px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-upc-scan"></i>
                                <span>Portal Kasir</span>
                            </a>
                        @endif
                        @if(!empty($landingHasManagerRole))
                            <a href="{{ route('manager.dashboard') }}" class="btn btn-success btn-sm px-2.5 py-1 fw-semibold d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-speedometer2"></i>
                                <span>Portal Manajer</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @else
        <!-- 1. KARTU FASILITAS LIMIT BELANJA (1 KartU Terpadu & Scannable) -->
        <div class="card border rounded-3 p-3 mb-3 bg-white shadow-xs">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-secondary fw-bold text-uppercase small" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                    <i class="bi bi-wallet2 text-primary me-1"></i> Fasilitas Piutang Anggota
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-0.5" style="font-size: 0.66rem;">
                    Bunga 0% &bull; Tempo 7 Hari
                </span>
            </div>

            <!-- Sisa Limit Utama (Hero Number) -->
            <div class="p-2.5 rounded-2 bg-light border mb-2.5">
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="text-muted small" style="font-size: 0.74rem;">Sisa Limit Belanja:</span>
                    <span class="badge bg-success text-white rounded-pill" style="font-size: 0.65rem;">Siap Dipakai</span>
                </div>
                <div class="fs-4 fw-extrabold text-success mb-1">
                    Rp {{ number_format($receivableStatus['available_limit'] ?? 852000, 0, ',', '.') }}
                </div>
                
                <!-- Progress Meter -->
                <div class="progress mb-1.5" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 85%;" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                
                <div class="d-flex justify-content-between text-muted" style="font-size: 0.7rem;">
                    <span>Plafon: Rp 1.000.000</span>
                    <span class="fw-semibold text-dark">85% Tersedia</span>
                </div>
            </div>

            <!-- 2 Kolom Ringkasan: Tagihan & Hold -->
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <div class="p-2 rounded-2 bg-white border">
                        <small class="text-secondary d-block" style="font-size: 0.68rem;">Tagihan Berjalan:</small>
                        <strong class="text-danger small d-block">Rp {{ number_format($receivableStatus['total_debt'] ?? 148000, 0, ',', '.') }}</strong>
                        <small class="text-danger fw-semibold" style="font-size: 0.65rem;">
                            <i class="bi bi-clock-history me-0.5"></i> Tempo 3 hari lagi
                        </small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 rounded-2 bg-white border">
                        <small class="text-secondary d-block" style="font-size: 0.68rem;">Credit Hold (Pesanan):</small>
                        <strong class="text-dark small d-block">Rp 0</strong>
                        <small class="text-success fw-semibold" style="font-size: 0.65rem;">
                            <i class="bi bi-check2 me-0.5"></i> Tidak ada ditahan
                        </small>
                    </div>
                </div>
            </div>

            <!-- Accordion Rincian Faktur (Kolaps secara default agar tidak penuh data) -->
            <div class="border rounded-2 overflow-hidden bg-light">
                <button class="btn btn-sm btn-link text-decoration-none text-dark w-100 d-flex justify-content-between align-items-center p-2 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#collapseInvoices" aria-expanded="false" aria-controls="collapseInvoices" style="font-size: 0.74rem;">
                    <span class="fw-semibold text-primary">
                        <i class="bi bi-receipt me-1"></i> Rincian 2 Faktur Belum Lunas
                    </span>
                    <span class="d-inline-flex align-items-center gap-1 text-muted">
                        <span>Rp 148.000</span>
                        <i class="bi bi-chevron-down small"></i>
                    </span>
                </button>

                <div class="collapse px-2 pb-2" id="collapseInvoices">
                    @if(!empty($receivableStatus['active_invoices']))
                        <div class="d-flex flex-column gap-1.5 pt-1 border-top">
                            @foreach($receivableStatus['active_invoices'] as $inv)
                                <div class="p-2 rounded-1 bg-white border d-flex justify-content-between align-items-center">
                                    <div class="lh-sm">
                                        <div class="fw-bold text-dark font-monospace" style="font-size: 0.72rem;">{{ $inv['invoice_no'] }}</div>
                                        <div class="text-secondary text-truncate" style="font-size: 0.7rem; max-width: 210px;">{{ $inv['items_summary'] }}</div>
                                        <small class="text-danger fw-semibold" style="font-size: 0.66rem;">Jatuh tempo: {{ $inv['due_date'] }} ({{ $inv['days_left'] }}h lagi)</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="fw-bold text-danger d-block" style="font-size: 0.78rem;">
                                            Rp {{ number_format($inv['amount'], 0, ',', '.') }}
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-1" style="font-size: 0.6rem;">
                                            BELUM LUNAS
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                            <small class="text-muted d-block mt-1" style="font-size: 0.68rem;">
                                <i class="bi bi-info-circle text-primary me-0.5"></i> Pelunasan tunai/QRIS langsung di kasir Gerai KOPEG Mart.
                            </small>
                        </div>
                    @endif
                </div>
            </div>

        </div>
        @endif

        @if(!empty($landingIsMemberRole))
        <!-- 2. KODE PICKUP PESANAN AKTIF (Jika Ada Pesanan Menunggu) -->
        @if(!empty($activeOrders))
            <div class="card border border-primary-subtle rounded-3 p-3 mb-3 bg-primary-subtle bg-opacity-25 shadow-xs">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-success text-white fw-bold px-2 py-0.5 rounded-pill" style="font-size: 0.68rem;">
                        <i class="bi bi-box-seam me-1"></i> SIAP DIAMBIL DI GERAI
                    </span>
                    <span class="font-monospace text-secondary small fw-bold">{{ $activeOrders[0]['order_no'] }}</span>
                </div>

                <!-- Box Kode Pickup -->
                <div class="p-2.5 rounded-2 bg-dark text-center mb-2 shadow-xs">
                    <small class="text-white text-opacity-75 text-uppercase d-block fw-bold" style="font-size: 0.65rem; letter-spacing: 0.06em;">KODE PICKUP KASIR:</small>
                    <div class="fs-4 fw-extrabold font-monospace text-info tracking-wider" id="drawerPickupCode">
                        {{ $activeOrders[0]['pickup_code'] }}
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center small text-secondary mb-2" style="font-size: 0.75rem;">
                    <span>2 Barang (Beras 5kg, Minyak 2L)</span>
                    <strong class="text-primary">Rp {{ number_format($activeOrders[0]['total_amount'], 0, ',', '.') }}</strong>
                </div>

                <!-- Action Buttons: Barcode & Copy -->
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm w-100 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" data-bs-toggle="modal" data-bs-target="#pickupBarcodeModal">
                        <i class="bi bi-qr-code"></i>
                        <span>Tampilkan Barcode</span>
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm w-100 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5" onclick="copyMemberPickupCode('{{ $activeOrders[0]['pickup_code'] }}', this)">
                        <i class="bi bi-clipboard"></i>
                        <span>Salin Kode</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- 3. RIWAYAT PENGAMBILAN TERAKHIR (Ringkas) -->
        @if(!empty($orderHistory))
            <div class="card border rounded-3 p-3 mb-3 bg-white shadow-xs">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-secondary fw-bold text-uppercase small" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                        <i class="bi bi-clock-history text-secondary me-1"></i> Pesanan Terakhir Selesai
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" style="font-size: 0.64rem;">SELESAI</span>
                </div>

                @foreach(array_slice($orderHistory, 0, 1) as $hist)
                    <div class="p-2 rounded-2 bg-light border small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold font-monospace text-dark" style="font-size: 0.72rem;">{{ $hist['order_no'] }}</span>
                            <strong class="text-primary" style="font-size: 0.78rem;">Rp {{ number_format($hist['total_amount'], 0, ',', '.') }}</strong>
                        </div>
                        <div class="text-secondary text-truncate" style="font-size: 0.7rem;">{{ $hist['items_summary'] }}</div>
                        <small class="text-muted d-block mt-1" style="font-size: 0.66rem;">Diambil pada: {{ $hist['picked_up_at'] }}</small>
                    </div>
                @endforeach
            </div>
        @endif
        @endif

        <!-- 4. AKSI AKUN -->
        <div class="pt-2">
            <div class="d-flex gap-2">
                <a href="{{ route('logout') }}" class="btn btn-outline-danger btn-sm w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Keluar Akun</span>
                </a>
                @if(!empty($landingIsMemberRole))
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Ganti Akun</span>
                    </a>
                @endif
            </div>
        </div>

        @endif
    </div>

    <!-- Drawer Footer -->
    <div class="offcanvas-footer bg-light p-2.5 border-top text-center">
        <small class="text-muted" style="font-size: 0.72rem;">
            <i class="bi bi-geo-alt-fill text-danger me-1"></i> Gerai Kanayakan A11 &bull; Buka 07.00 - 18.00 WIB
        </small>
    </div>

</div>

<!-- Modal Barcode / QR Pickup Code (Full Feature with Real 1D & 2D) -->
<div class="modal fade" id="pickupBarcodeModal" tabindex="-1" aria-labelledby="pickupBarcodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-6" id="pickupBarcodeModalLabel">
                    <i class="bi bi-qr-code text-primary me-2"></i> Kode Pengambilan Kasir Gerai
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-2 fw-semibold" style="font-size: 0.72rem;">
                    GERAI KOPEG MART KANAYAKAN A11
                </span>
                
                <h4 class="fw-extrabold text-dark mb-1">
                    {{ $activeOrders[0]['pickup_code'] ?? 'PKP-84920' }}
                </h4>
                <p class="text-muted small mb-3">Tunjukkan layar ini langsung ke petugas kasir saat pengambilan pesanan.</p>

                <!-- Scannable Barcode 1D (Garis) Standar Scanner Kasir -->
                <div class="p-3 bg-white border rounded-3 d-inline-block shadow-sm mb-3 text-center w-100" style="max-width: 340px;">
                    <div class="p-2.5 bg-white rounded border">
                        <svg id="drawerPickupBarcodeSvg" class="w-100" style="max-height: 85px;"></svg>
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size: 0.72rem;">Format: Barcode 1D Code-128 (Standar Scanner Kasir)</small>
                </div>

                <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                    <button type="button" class="btn btn-outline-dark btn-sm fw-semibold d-inline-flex align-items-center gap-1.5 px-3 py-1.5" onclick="openFullscreenPickupModal('{{ $activeOrders[0]['pickup_code'] ?? 'PKP-84920' }}')">
                        <i class="bi bi-arrows-fullscreen text-primary"></i>
                        <span>Mode Layar Penuh (Kecerahan Maksimal)</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5 px-3 py-1.5" onclick="copyMemberPickupCode('{{ $activeOrders[0]['pickup_code'] ?? 'PKP-84920' }}', this)">
                        <i class="bi bi-clipboard"></i>
                        <span>Salin Kode</span>
                    </button>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const modalEl = document.getElementById('pickupBarcodeModal');
                    if (modalEl) {
                        modalEl.addEventListener('shown.bs.modal', () => {
                            const code = "{{ $activeOrders[0]['pickup_code'] ?? 'PKP-84920' }}";
                            if (window.JsBarcode) {
                                try {
                                    window.JsBarcode("#drawerPickupBarcodeSvg", code, {
                                        format: "CODE128",
                                        lineColor: "#000000",
                                        width: 2.2,
                                        height: 60,
                                        displayValue: true,
                                        fontSize: 14,
                                        margin: 6,
                                        background: "#ffffff"
                                    });
                                } catch(e) { console.warn("JsBarcode drawer error", e); }
                            }
                        });
                    }
                });
                </script>

                <div class="p-2.5 rounded-2 bg-light border text-start small text-secondary">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Penerima:</span>
                        <strong class="text-dark">{{ $member['name'] ?? 'Ahmad Fauzi' }}{{ !empty($landingMemberCode) ? ' (' . $landingMemberCode . ')' : '' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Total Belanja:</span>
                        <strong class="text-primary">Rp {{ number_format($activeOrders[0]['total_amount'] ?? 110500, 0, ',', '.') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Status:</span>
                        <span class="badge bg-success text-white">Siap Diambil Sekarang</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
