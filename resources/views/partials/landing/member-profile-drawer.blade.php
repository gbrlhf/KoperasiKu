{{-- ==============================================================================
   member-profile-drawer.blade.php - Offcanvas Profil & Cek Tagihan Anggota
   Menampilkan:
   - Kartu Tanda Anggota Digital KOPEG Mart
   - Sisa limit belanja piutang & status tagihan berjalan (Tempo 7 Hari Bunga 0%)
   - Rincian faktur piutang aktif
   - Tiket kode pickup siap ambil
   - Transparansi alokasi limit kredit (Limit Global, Tagihan Berjalan, Credit Hold, Sisa Limit)
   - Riwayat pesanan selesai
   - Akses ganti akun & portal kasir/manajer
   ============================================================================== --}}

<div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="memberProfileDrawer" aria-labelledby="memberProfileDrawerLabel" style="width: 480px; max-width: 92vw;">
    
    <!-- Drawer Header -->
    <div class="offcanvas-header bg-light border-bottom py-3 px-3 px-md-4">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="bi bi-person-badge fs-5"></i>
            </div>
            <div class="lh-sm">
                <h6 class="offcanvas-title fw-bold text-dark mb-0" id="memberProfileDrawerLabel">
                    Profil Anggota & Tagihan
                </h6>
                <small class="text-muted" style="font-size: 0.73rem;">Koperasi Konsumen Pegawai POLMAN Bandung</small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup jendela profil"></button>
    </div>

    <!-- Drawer Body -->
    <div class="offcanvas-body p-3 p-md-4">
        
        <!-- 1. VIRTUAL MEMBER CARD (Kartu Tanda Anggota Digital) -->
        <div class="p-3.5 rounded-3 text-white mb-4 position-relative overflow-hidden shadow-sm" style="background: linear-gradient(135deg, #091724 0%, #0f2942 55%, #1d4ed8 100%); border: 1px solid rgba(255, 255, 255, 0.12);">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-2">
                    <!-- Chip -->
                    <div style="width: 32px; height: 24px; background: linear-gradient(135deg, #fcd34d 0%, #d97706 100%); border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.3); position: relative;">
                        <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: rgba(0,0,0,0.3);"></div>
                    </div>
                    <span class="badge bg-white bg-opacity-20 text-white border border-white border-opacity-25" style="font-size: 0.7rem;">
                        KOPEG MART
                    </span>
                </div>
                <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 rounded-pill px-2.5 py-0.5" style="font-size: 0.68rem;">
                    <i class="bi bi-patch-check-fill text-warning me-1"></i> {{ $member['status'] ?? 'TERVERIFIKASI' }}
                </span>
            </div>

            <!-- Member ID & Name -->
            <div class="mb-2">
                <small class="text-white text-opacity-60 text-uppercase d-block fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.08em;">Nomor Anggota</small>
                <div class="fs-5 fw-bold font-monospace text-white tracking-widest">
                    {{ $member['id'] ?? 'EMP-001' }} &bull; 8821 &bull; 9042
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-end pt-2 border-top border-white border-opacity-10">
                <div>
                    <small class="text-white text-opacity-60 text-uppercase d-block" style="font-size: 0.62rem;">Nama Anggota</small>
                    <span class="fw-bold text-white small">{{ strtoupper($member['name'] ?? 'Ahmad Fauzi') }}</span>
                    <small class="text-white text-opacity-75 d-block" style="font-size: 0.68rem;">{{ $member['unit'] ?? 'POLMAN Bandung' }}</small>
                </div>
                <div class="text-end">
                    <small class="text-white text-opacity-60 text-uppercase d-block" style="font-size: 0.62rem;">Bergabung</small>
                    <span class="text-white fw-semibold small">{{ $member['join_date'] ?? 'Maret 2021' }}</span>
                </div>
            </div>

        </div>

        <!-- 2. STATUS PIUTANG & SISA LIMIT BELANJA (Tempo 7 Hari) -->
        <div class="card border rounded-3 p-3 mb-3 bg-white shadow-xs">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-1.5">
                    <i class="bi bi-wallet2 text-primary fs-5"></i>
                    <h6 class="fw-bold text-dark mb-0 fs-6">Fasilitas Piutang Anggota</h6>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-0.5" style="font-size: 0.68rem;">
                    Bunga 0% &bull; Tempo 7 Hari
                </span>
            </div>

            <div class="row g-2 mb-2">
                <!-- Limit Tersedia -->
                <div class="col-6">
                    <div class="p-2.5 rounded-2 bg-light border">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Sisa Limit Belanja:</small>
                        <span class="fs-6 fw-extrabold text-success">
                            Rp {{ number_format($receivableStatus['available_limit'] ?? 852000, 0, ',', '.') }}
                        </span>
                        <div class="progress mt-1.5" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: 85%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Tagihan Aktif -->
                <div class="col-6">
                    <div class="p-2.5 rounded-2 bg-light border">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Tagihan Berjalan:</small>
                        <span class="fs-6 fw-extrabold text-danger">
                            Rp {{ number_format($receivableStatus['total_debt'] ?? 148000, 0, ',', '.') }}
                        </span>
                        <small class="text-danger fw-semibold d-block mt-1" style="font-size: 0.68rem;">
                            <i class="bi bi-clock-history me-0.5"></i> Tempo 3 hari lagi
                        </small>
                    </div>
                </div>
            </div>

            <!-- Invoices Accordion / Collapse -->
            <div class="border rounded-2 p-2 bg-light mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1.5">
                    <small class="fw-bold text-dark" style="font-size: 0.74rem;">Rincian 2 Faktur Tagihan:</small>
                    <small class="text-muted" style="font-size: 0.7rem;">Jatuh tempo: 19 & 21 Nov 2026</small>
                </div>
                
                @if(!empty($receivableStatus['active_invoices']))
                    <div class="d-flex flex-column gap-1.5">
                        @foreach($receivableStatus['active_invoices'] as $inv)
                            <div class="d-flex justify-content-between align-items-center p-2 rounded-1 bg-white border small">
                                <div>
                                    <div class="fw-bold text-primary font-monospace" style="font-size: 0.75rem;">{{ $inv['invoice_no'] }}</div>
                                    <div class="text-secondary" style="font-size: 0.72rem;">{{ $inv['items_summary'] }}</div>
                                    <small class="text-muted" style="font-size: 0.68rem;">Tempo: {{ $inv['due_date'] }} ({{ $inv['days_left'] }} hari lagi)</small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-extrabold text-danger d-block" style="font-size: 0.8rem;">
                                        Rp {{ number_format($inv['amount'], 0, ',', '.') }}
                                    </span>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-1" style="font-size: 0.62rem;">
                                        BELUM LUNAS
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Cara Bayar Alert -->
            <small class="text-muted d-block" style="font-size: 0.72rem;">
                <i class="bi bi-info-circle-fill text-primary me-1"></i> Pelunasan dapat dibayarkan langsung secara tunai atau QRIS di kasir gerai KOPEG Mart.
            </small>
        </div>

        <!-- 3. KODE PICKUP PESANAN AKTIF -->
        @if(!empty($activeOrders))
            <div class="card border border-primary rounded-3 p-3 mb-3 bg-primary bg-opacity-10 shadow-xs">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="badge bg-success text-white rounded-pill px-2 py-0.5 fw-bold" style="font-size: 0.68rem;">
                            <i class="bi bi-box-seam me-1"></i> SIAP DIAMBIL
                        </span>
                        <span class="text-dark fw-bold small">{{ $activeOrders[0]['order_no'] }}</span>
                    </div>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ $activeOrders[0]['date'] }}</small>
                </div>

                <div class="p-2.5 rounded-2 bg-dark text-center mb-2">
                    <small class="text-white text-opacity-75 text-uppercase d-block fw-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">KODE PICKUP KASIR:</small>
                    <div class="fs-4 fw-extrabold font-monospace text-info tracking-wider" id="drawerPickupCode">
                        {{ $activeOrders[0]['pickup_code'] }}
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center small text-secondary mb-2" style="font-size: 0.76rem;">
                    <span>2 Barang (Beras Ramos 5kg, Minyak 2L)</span>
                    <strong class="text-primary">Rp {{ number_format($activeOrders[0]['total_amount'], 0, ',', '.') }}</strong>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm w-100 fw-semibold d-inline-flex align-items-center justify-content-center gap-1" onclick="copyMemberPickupCode('{{ $activeOrders[0]['pickup_code'] }}', this)">
                        <i class="bi bi-clipboard"></i>
                        <span>Salin Kode</span>
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm w-100 fw-semibold d-inline-flex align-items-center justify-content-center gap-1" data-bs-toggle="modal" data-bs-target="#pickupBarcodeModal">
                        <i class="bi bi-qr-code"></i>
                        <span>Tampilkan Barcode</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- 4. TRANSPARANSI FASILITAS LIMIT PIUTANG (MVP KONSUMSI) -->
        <div class="card border rounded-3 p-3 mb-3 bg-white shadow-xs">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold text-dark mb-0 fs-6">
                    <i class="bi bi-shield-check text-primary me-1"></i> Transparansi Alokasi Limit Kredit
                </h6>
                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">
                    Kebijakan Seragam Aktif
                </span>
            </div>
            <p class="text-muted mb-2.5" style="font-size: 0.72rem;">
                Fasilitas piutang belanja tempo 7 hari kalender bunga 0% khusus anggota resmi KOPEG Mart.
            </p>

            <div class="row g-2 mb-2">
                <!-- Limit Global Koperasi -->
                <div class="col-6">
                    <div class="p-2.5 rounded-2 bg-light border h-100">
                        <small class="text-secondary d-block fw-semibold" style="font-size: 0.68rem;">Limit Global Koperasi</small>
                        <span class="fw-bold text-dark small">Rp 1.000.000</span>
                        <small class="text-muted d-block" style="font-size: 0.65rem;">Plafon resmi seragam</small>
                    </div>
                </div>

                <!-- Tagihan Berjalan -->
                <div class="col-6">
                    <div class="p-2.5 rounded-2 bg-light border h-100">
                        <small class="text-secondary d-block fw-semibold" style="font-size: 0.68rem;">Tagihan Berjalan</small>
                        <span class="fw-bold text-danger small">Rp {{ number_format($receivableStatus['total_debt'] ?? 148000, 0, ',', '.') }}</span>
                        <small class="text-danger d-block" style="font-size: 0.65rem;">2 faktur aktif belum lunas</small>
                    </div>
                </div>

                <!-- Credit Hold (Pesanan Belum Diambil) -->
                <div class="col-6">
                    <div class="p-2.5 rounded-2 bg-light border h-100">
                        <small class="text-secondary d-block fw-semibold" style="font-size: 0.68rem;">Credit Hold (Pesanan Belum Diambil)</small>
                        <span class="fw-bold text-warning-emphasis small">Rp 0</span>
                        <small class="text-muted d-block" style="font-size: 0.65rem;">Tidak ada pesanan ditahan</small>
                    </div>
                </div>

                <!-- Sisa Limit Tersedia -->
                <div class="col-6">
                    <div class="p-2.5 rounded-2 bg-success bg-opacity-10 border border-success border-opacity-25 h-100">
                        <small class="text-success d-block fw-bold" style="font-size: 0.68rem;">Sisa Limit Tersedia</small>
                        <span class="fw-extrabold text-success small">Rp {{ number_format($receivableStatus['available_limit'] ?? 852000, 0, ',', '.') }}</span>
                        <small class="text-success d-block fw-semibold" style="font-size: 0.65rem;">Siap dipakai belanja</small>
                    </div>
                </div>
            </div>

            <div class="p-2 rounded-2 bg-light border-start border-3 border-primary" style="font-size: 0.7rem;">
                <span class="text-secondary">Rumus Limit: <strong>Sisa Limit</strong> = Limit Global (Rp 1.000.000) - Tagihan Berjalan (Rp 148.000) - Credit Hold (Rp 0)</span>
            </div>
        </div>

        <!-- 5. RIWAYAT BELANJA TERAKHIR -->
        @if(!empty($orderHistory))
            <div class="card border rounded-3 p-3 mb-3 bg-white shadow-xs">
                <h6 class="fw-bold text-dark mb-2 fs-6">
                    <i class="bi bi-clock-history text-secondary me-1"></i> Riwayat Pengambilan Terakhir
                </h6>
                <div class="d-flex flex-column gap-2">
                    @foreach($orderHistory as $hist)
                        <div class="p-2 rounded-2 bg-light border small">
                            <div class="d-flex justify-content-between align-items-center mb-0.5">
                                <span class="fw-bold font-monospace text-dark" style="font-size: 0.72rem;">{{ $hist['order_no'] }}</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.62rem;">SELESAI</span>
                            </div>
                            <div class="text-secondary lh-sm" style="font-size: 0.72rem;">{{ $hist['items_summary'] }}</div>
                            <div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.68rem;">
                                <span>{{ $hist['date'] }}</span>
                                <strong class="text-primary">Rp {{ number_format($hist['total_amount'], 0, ',', '.') }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- 6. AKSES PORTAL LAIN & GANTI AKUN -->
        <div class="p-3 rounded-3 bg-light border">
            <small class="text-muted fw-bold text-uppercase d-block mb-2" style="font-size: 0.68rem;">Akses Sistem Terkait:</small>
            <div class="d-flex flex-column gap-1.5">
                <a href="{{ route('cashier.pos') }}" class="btn btn-outline-success btn-sm text-start d-flex align-items-center justify-content-between py-1.5">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-shop"></i>
                        <span>Buka Portal Kasir (Stok & POS)</span>
                    </span>
                    <i class="bi bi-arrow-right small"></i>
                </a>
                <a href="{{ route('manager.dashboard') }}" class="btn btn-outline-danger btn-sm text-start d-flex align-items-center justify-content-between py-1.5">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-speedometer2"></i>
                        <span>Buka Portal Manajer (Analitik)</span>
                    </span>
                    <i class="bi bi-arrow-right small"></i>
                </a>
                <a href="{{ route('login') }}" class="btn btn-light btn-sm text-start d-flex align-items-center justify-content-between py-1.5 border text-secondary mt-1">
                    <span class="d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Ganti Akun Login</span>
                    </span>
                    <i class="bi bi-chevron-right small"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- Drawer Footer -->
    <div class="offcanvas-footer bg-light p-3 border-top text-center">
        <small class="text-muted" style="font-size: 0.75rem;">
            <i class="bi bi-geo-alt text-primary me-1"></i> Gerai KOPEG Mart Kanayakan A11 &bull; Buka 07.00 - 18.00 WIB
        </small>
    </div>

</div>

<!-- Modal Barcode / QR Pickup Code -->
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
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-2 fw-semibold">
                    GERAI KOPEG MART KANAYAKAN A11
                </span>
                
                <h4 class="fw-extrabold text-dark mb-1">
                    {{ $activeOrders[0]['pickup_code'] ?? 'PKP-84920' }}
                </h4>
                <p class="text-muted small mb-3">Tunjukkan layar ini langsung ke petugas kasir saat pengambilan pesanan.</p>

                <!-- Real Scannable Barcode & QR Code Graphic -->
                <div class="p-3 bg-white border rounded-3 d-inline-block shadow-sm mb-3 text-center w-100" style="max-width: 320px;">
                    <!-- Nav Tabs Barcode 1D / QR 2D -->
                    <ul class="nav nav-pills nav-fill bg-light p-1 rounded-2 mb-2.5" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-2 fw-semibold" style="font-size: 0.72rem;" id="tab-pickup-barcode" data-bs-toggle="pill" data-bs-target="#pane-pickup-barcode" type="button" role="tab">
                                <i class="bi bi-upc me-1"></i> Barcode 1D (Garis)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-2 fw-semibold" style="font-size: 0.72rem;" id="tab-pickup-qr" data-bs-toggle="pill" data-bs-target="#pane-pickup-qr" type="button" role="tab">
                                <i class="bi bi-qr-code me-1"></i> QR Code 2D
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Tab 1: Real 1D Code-128 Barcode -->
                        <div class="tab-pane fade show active text-center" id="pane-pickup-barcode" role="tabpanel">
                            <div class="p-2 bg-white rounded border">
                                <svg id="drawerPickupBarcodeSvg" class="w-100" style="max-height: 85px;"></svg>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">Format: Code-128 (Standar Scanner Kasir)</small>
                        </div>
                        <!-- Tab 2: Real 2D QR Code -->
                        <div class="tab-pane fade text-center" id="pane-pickup-qr" role="tabpanel">
                            <div class="p-2 bg-white rounded border d-inline-block">
                                <canvas id="drawerPickupQrCanvas" style="width: 140px; height: 140px;"></canvas>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.7rem;">Format: QR Code (Dapat di-scan Kamera HP / POS)</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-2 mb-2 flex-wrap">
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
                            if (window.QRCode) {
                                const canvas = document.getElementById('drawerPickupQrCanvas');
                                if (canvas) {
                                    window.QRCode.toCanvas(canvas, code, {
                                        width: 140,
                                        margin: 2,
                                        color: { dark: '#000000', light: '#ffffff' }
                                    }, (err) => { if (err) console.warn(err); });
                                }
                            }
                        });
                    }
                });
                </script>

                <div class="p-2.5 rounded-2 bg-light border text-start small text-secondary">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Penerima:</span>
                        <strong class="text-dark">{{ $member['name'] ?? 'Ahmad Fauzi' }} ({{ $member['id'] ?? 'EMP-001' }})</strong>
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
