@extends('layouts.app')

@section('title', 'Pelayanan Cicilan & Pelunasan Piutang')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold mb-0 text-dark">Pelayanan Cicilan & Pelunasan Piutang</h4>
            <span class="badge bg-success text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-wallet2 me-1"></i> Modul Kasir FR-08B
            </span>
        </div>
        <p class="text-secondary small mb-0">Cari anggota, periksa faktur tempo 7 hari, catat penerimaan cicilan/pelunasan tunai atau transfer, dan pulihkan limit belanja anggota.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-white text-secondary border px-3 py-2 small shadow-xs">
            <i class="bi bi-shield-check me-1 text-primary"></i> Plafon Global: <strong>Rp {{ number_format($globalCreditLimit ?? 1000000, 0, ',', '.') }}</strong>
        </span>
    </div>
</div>
@endsection

@section('content')

<!-- 1. PENCARIAN & PEMILIHAN ANGGOTA -->
<div class="card border-0 shadow-sm bg-white p-3 p-md-4 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-12 col-md-5">
            <label class="form-label small fw-bold text-secondary mb-1">
                <i class="bi bi-person-search text-primary me-1"></i> Cari Nomor Anggota / ID Karyawan:
            </label>
            <select class="form-select form-select-sm fw-semibold" id="memberSelect" onchange="onSelectMember(this.value)">
                <option value="">-- Pilih Anggota yang Dilayani di Kasir --</option>
                @foreach($debtorMembers as $m)
                    <option value="{{ $m['id'] }}">
                        {{ $m['id'] }} | {{ $m['name'] }} — {{ $m['unit'] }} ({{ $m['total_debt'] > 0 ? 'Hutang: Rp ' . number_format($m['total_debt'], 0, ',', '.') : 'Lunas (Rp 0)' }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-7">
            <small class="text-muted d-block" style="font-size: 0.72rem;">Uji Coba Cepat Kasir (Pilih Simulasi Anggota):</small>
            <div class="d-flex gap-1.5 flex-wrap mt-1">
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.75rem;" onclick="quickPickMember('EMP-001')">
                    <i class="bi bi-person-check text-primary me-1"></i>Ahmad Fauzi (2 Faktur Normal)
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.75rem;" onclick="quickPickMember('EMP-028')">
                    <i class="bi bi-clock-history text-warning-emphasis me-1"></i>Dewi Lestari (3 Faktur / Cicil)
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2" style="font-size: 0.75rem;" onclick="quickPickMember('EMP-035')">
                    <i class="bi bi-exclamation-octagon text-danger me-1"></i>Hendra Wijaya (OVERDUE >7 Hari)
                </button>
                <button type="button" class="btn btn-outline-success btn-sm py-1 px-2" style="font-size: 0.75rem;" onclick="quickPickMember('EMP-014')">
                    <i class="bi bi-check-circle text-success me-1"></i>Siti Rahma (Saldo Rp 0)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 2. EMPTY STATE SEBELUM ANGGOTA DIPILIH -->
<div id="emptyMemberState" class="card border-0 shadow-sm bg-white p-5 text-center mb-4">
    <div class="py-4">
        <div class="p-3.5 rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
            <i class="bi bi-wallet2 fs-1"></i>
        </div>
        <h5 class="fw-bold text-dark mb-2">Pilih Anggota Koperasi Terlebih Dahulu</h5>
        <p class="text-secondary small mb-3 mx-auto" style="max-width: 480px;">
            Gunakan kotak pencarian atau tombol simulasi di atas untuk memuat kartu plafon kredit, daftar faktur piutang yang belum lunas, dan form pencatatan cicilan kasir.
        </p>
        <span class="badge bg-light text-secondary border px-3 py-1.5 small">
            <i class="bi bi-info-circle text-primary me-1"></i> Sesuai Aturan: Jatuh tempo seragam 7 hari kalender sejak pickup
        </span>
    </div>
</div>

<!-- 3. KONTEN PELAYANAN KASIR (DITAMPILKAN SETELAH ANGGOTA DIPILIH) -->
<div id="memberServiceContent" class="d-none">

    <!-- A. KARTU INFORMASI PLAFON & STATUS KREDIT ANGGOTA -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="fw-bold text-dark mb-0" id="cardMemberName">-</h5>
                            <span class="badge font-monospace" id="cardMemberIdBadge">EMP-000</span>
                        </div>
                        <small class="text-muted" id="cardMemberUnit">-</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge py-1.5 px-2.5" id="cardCreditStatusBadge">STATUS</span>
                    </div>
                </div>

                <div class="row g-2 g-md-3">
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 rounded-2 bg-light border text-center h-100">
                            <small class="text-secondary d-block fw-semibold" style="font-size: 0.68rem;">Plafon Limit Global</small>
                            <span class="fw-bold text-dark font-monospace fs-6">Rp 1.000.000</span>
                            <small class="text-muted d-block" style="font-size: 0.65rem;">Limit seragam koperasi</small>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 rounded-2 bg-light border text-center h-100">
                            <small class="text-secondary d-block fw-semibold" style="font-size: 0.68rem;">Total Saldo Piutang</small>
                            <span class="fw-extrabold text-danger font-monospace fs-6" id="cardTotalDebt">Rp 0</span>
                            <small class="text-danger d-block" style="font-size: 0.65rem;" id="cardInvoiceCount">0 Faktur Aktif</small>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 rounded-2 bg-success bg-opacity-10 border border-success border-opacity-25 text-center h-100">
                            <small class="text-success d-block fw-bold" style="font-size: 0.68rem;">Sisa Limit Tersedia</small>
                            <span class="fw-extrabold text-success font-monospace fs-6" id="cardAvailableLimit">Rp 0</span>
                            <small class="text-success d-block" style="font-size: 0.65rem;">Limit belanja saat ini</small>
                        </div>
                    </div>
                    <div class="col-6 col-sm-3">
                        <div class="p-2.5 rounded-2 bg-light border text-center h-100">
                            <small class="text-secondary d-block fw-semibold" style="font-size: 0.68rem;">Jatuh Tempo Terdekat</small>
                            <span class="fw-bold text-dark font-monospace" style="font-size: 0.85rem;" id="cardEarliestDueDate">-</span>
                            <small class="d-block" style="font-size: 0.65rem;" id="cardDueNotice">-</small>
                        </div>
                    </div>
                </div>

                <!-- Overdue Alert Banner (Ditampilkan jika anggota kena Credit Hold) -->
                <div id="overdueAlertBanner" class="alert alert-danger d-flex align-items-center gap-2 mt-3 mb-0 py-2.5 px-3 small d-none" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 text-danger flex-shrink-0"></i>
                    <div>
                        <strong>Fasilitas Piutang Terkunci (Credit Hold):</strong> Anggota memiliki tagihan tertunggak yang telah melewati jatuh tempo 7 hari kalender. Sesuai PRD, order kredit baru ditolak hingga tagihan dilunasi.
                    </div>
                </div>
            </div>
        </div>

        <!-- Info Cepat Akuntansi Koperasi -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100 border-start border-4 border-info">
                <h6 class="fw-bold text-dark mb-2 fs-6">
                    <i class="bi bi-journal-text text-primary me-1"></i> Ketentuan Pembukuan Kasir
                </h6>
                <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary mb-3" style="font-size: 0.76rem;">
                    <li class="d-flex align-items-start gap-1.5">
                        <i class="bi bi-check-circle-fill text-success mt-0.5"></i>
                        <span>Pelunasan piutang diakui sebagai <strong>Kas Masuk</strong>, BUKAN omzet baru (omzet telah diakui saat serah terima barang).</span>
                    </li>
                    <li class="d-flex align-items-start gap-1.5">
                        <i class="bi bi-check-circle-fill text-success mt-0.5"></i>
                        <span>Alokasi otomatis memprioritaskan faktur dengan <strong>jatuh tempo terawal (FIFO)</strong>.</span>
                    </li>
                    <li class="d-flex align-items-start gap-1.5">
                        <i class="bi bi-check-circle-fill text-success mt-0.5"></i>
                        <span>Setiap rupiah yang dilunasi langsung <strong>memulihkan sisa limit belanja</strong> anggota.</span>
                    </li>
                </ul>
                <div class="p-2 rounded bg-light border small text-muted" style="font-size: 0.72rem;">
                    Kasir bertugas: <strong>Budi Santoso (Kasir 01 Gerai Kanayakan)</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- B. EMPTY STATE JIKA ANGGOTA TIDAK PUNYA HUTANG (SALDO = Rp 0) -->
    <div id="zeroDebtState" class="card border-0 shadow-sm bg-white p-5 text-center mb-4 d-none">
        <div class="py-3">
            <div class="p-3 rounded-circle bg-success bg-opacity-10 text-success d-inline-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px;">
                <i class="bi bi-patch-check fs-2"></i>
            </div>
            <h6 class="fw-bold text-dark mb-1">Anggota Tidak Memiliki Tagihan Piutang</h6>
            <p class="text-secondary small mb-0">
                Seluruh tagihan transaksi anggota telah lunas (Saldo Piutang: Rp 0). Plafon kredit belanja aktif penuh sebesar Rp 1.000.000.
            </p>
        </div>
    </div>

    <!-- C. DAFTAR FAKTUR & FORM CICILAN KASIR -->
    <div id="debtDetailsContainer" class="row g-3 mb-4">
        <!-- Tabel Faktur Piutang -->
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm bg-white h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title fw-bold text-dark mb-0 fs-6">Daftar Faktur Piutang Belum Lunas</h6>
                        <small class="text-muted" style="font-size: 0.72rem;">Faktur terbuka anggota yang wajib dilunasi</small>
                    </div>
                    <span class="badge bg-light text-secondary border small" id="tableInvoiceBadge">0 Faktur</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                        <thead class="table-light">
                            <tr>
                                <th>No. Faktur</th>
                                <th>Tgl Pickup</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-end">Sisa Tagihan</th>
                                <th class="text-center">Status</th>
                                <th class="text-end" style="width: 140px;">Alokasi Bayar</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
                            <!-- Populated via JavaScript renderInvoicesTable() -->
                        </tbody>
                        <tfoot class="table-light fw-bold font-monospace">
                            <tr>
                                <td colspan="3" class="text-end">Total Saldo Terbuka:</td>
                                <td class="text-end text-danger fs-6" id="tableTotalBalance">Rp 0</td>
                                <td class="text-center">Total Alokasi:</td>
                                <td class="text-end text-primary fs-6" id="tableTotalAllocated">Rp 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="card-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center small text-muted" style="font-size: 0.72rem;">
                    <span><i class="bi bi-info-circle me-1"></i>Prioritas pelunasan default: Tanggal jatuh tempo terlama (FIFO).</span>
                    <span id="allocationMatchBadge" class="badge bg-success-subtle text-success border border-success-subtle">Alokasi Pas</span>
                </div>
            </div>
        </div>

        <!-- Form Penerimaan Cicilan / Pelunasan -->
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm bg-white p-3 p-md-4 h-100">
                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                    <span class="d-flex align-items-center gap-1.5">
                        <i class="bi bi-cash-stack text-success"></i>
                        <span>Form Penerimaan Cicilan</span>
                    </span>
                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;">KAS-IN</span>
                </h6>

                <!-- Input Nominal Pembayaran -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Nominal Pembayaran Diterima (Rp):</label>
                    <div class="input-group input-group-sm mb-1.5">
                        <span class="input-group-text bg-light fw-bold">Rp</span>
                        <input type="number" class="form-control form-control-sm fs-6 fw-bold font-monospace" id="paymentAmountInput" placeholder="Masukkan nominal..." oninput="onPaymentAmountInput(this.value)">
                    </div>

                    <!-- Tombol Cepat Nominal -->
                    <div class="d-flex gap-1 mb-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm py-0.5 px-2 text-nowrap" style="font-size: 0.7rem;" onclick="setPayAllDebt()">
                            <i class="bi bi-check2-all me-1"></i>Lunasi Semua
                        </button>
                        <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-nowrap" style="font-size: 0.7rem;" onclick="setPayEarliestInvoice()">
                            Faktur Terlama
                        </button>
                        <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-nowrap" style="font-size: 0.7rem;" onclick="setPayAmount(50000)">
                            Rp 50k
                        </button>
                        <button type="button" class="btn btn-light border btn-sm py-0.5 px-2 text-nowrap" style="font-size: 0.7rem;" onclick="setPayAmount(100000)">
                            Rp 100k
                        </button>
                    </div>
                    <div id="paymentAmountError" class="text-danger small d-none" style="font-size: 0.72rem;">
                        <i class="bi bi-exclamation-circle me-1"></i>Nominal pembayaran tidak valid.
                    </div>
                </div>

                <!-- Opsi Mode Alokasi -->
                <div class="mb-3 p-2.5 rounded-2 bg-light border">
                    <label class="form-label small fw-bold text-secondary mb-1.5 d-block">Strategi Alokasi Tagihan:</label>
                    <div class="d-flex gap-3">
                        <div class="form-check small">
                            <input class="form-check-input" type="radio" name="allocationMode" id="allocAuto" value="auto" checked onchange="toggleAllocationMode('auto')">
                            <label class="form-check-label fw-semibold text-dark" for="allocAuto">
                                Otomatis (FIFO)
                            </label>
                        </div>
                        <div class="form-check small">
                            <input class="form-check-input" type="radio" name="allocationMode" id="allocManual" value="manual" onchange="toggleAllocationMode('manual')">
                            <label class="form-check-label fw-semibold text-dark" for="allocManual">
                                Manual per Faktur
                            </label>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size: 0.68rem;" id="allocModeHelp">
                        Sistem memprioritaskan pembayaran ke faktur yang memiliki tanggal jatuh tempo terawal terlebih dahulu.
                    </small>
                </div>

                <!-- Metode Bayar Cicilan -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Metode Bayar Pelunasan:</label>
                    <select class="form-select form-select-sm fw-semibold mb-2" id="repaymentMethod" onchange="onRepaymentMethodChanged(this.value)">
                        <option value="CASH">Tunai di Kasir (Cash)</option>
                        <option value="BANK_TRANSFER">Transfer Bank / QRIS Koperasi</option>
                    </select>

                    <!-- A. Input Tunai -->
                    <div id="repayCashBox">
                        <div class="d-flex justify-content-between align-items-center p-2 rounded-2 bg-light border small">
                            <span class="text-secondary fw-semibold">Verifikasi Tunai:</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Langsung Masuk Kas</span>
                        </div>
                    </div>

                    <!-- B. Input Transfer Bank -->
                    <div id="repayTransferBox" class="d-none">
                        <div class="mb-2">
                            <label class="form-label text-muted d-block mb-1" style="font-size: 0.72rem;">Nomor Referensi Mutasi Bank / QRIS Kasir:</label>
                            <input type="text" class="form-control form-control-sm font-monospace text-uppercase" id="repayTransferRef" placeholder="Contoh: TRX-BCA-98124 / QRIS-41029" oninput="validateRepaymentForm()">
                        </div>
                        <div class="form-check p-2.5 rounded-2 bg-light border mb-2">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="repayTransferCheck" onchange="validateRepaymentForm()">
                            <label class="form-check-label text-dark small fw-semibold" for="repayTransferCheck">
                                Dana transfer sudah dipastikan masuk ke rekening koperasi
                            </label>
                            <small class="text-muted d-block mt-0.5" style="font-size: 0.68rem;">Sesuai PRD, cicilan transfer yang belum diverifikasi tidak boleh mengurangi saldo piutang.</small>
                        </div>
                    </div>
                </div>

                <!-- Ringkasan Dampak Finansial Pelunasan -->
                <div class="p-2.5 rounded-2 bg-light border mb-3 small" style="font-size: 0.75rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Saldo Piutang Setelah Bayar:</span>
                        <strong class="text-dark font-monospace" id="previewNewDebt">Rp 0</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Pemulihan Sisa Limit Belanja:</span>
                        <strong class="text-success font-monospace" id="previewNewLimit">Rp 0</strong>
                    </div>
                </div>

                <!-- Tombol Submit Simpan Pembayaran Cicilan -->
                <button type="button" class="btn btn-success w-100 py-2.5 fw-semibold d-inline-flex align-items-center justify-content-center gap-1.5 shadow-xs" id="btnSubmitRepayment" onclick="openRepaymentReceiptModal()">
                    <i class="bi bi-check2-circle fs-6"></i>
                    <span>Simpan Pembayaran & Terbitkan Struk Kas Masuk</span>
                </button>
            </div>
        </div>
    </div>

</div>

<!-- ==============================================================================
     MODAL STRUK BUKTI PELUNASAN / CICILAN PIUTANG KASIR (REPLACES ALERT)
     ============================================================================== -->
<div class="modal fade" id="receiptCicilanModal" tabindex="-1" aria-labelledby="receiptCicilanModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white py-3 px-3 px-md-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-patch-check-fill fs-5"></i>
                    <h6 class="modal-title fw-bold mb-0" id="receiptCicilanModalLabel">Bukti Kas Masuk & Pelunasan Piutang</h6>
                </div>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Struk Digital Koperasi -->
                <div class="border rounded-3 p-3 bg-light mb-3 font-monospace" style="font-size: 0.78rem;">
                    <div class="text-center pb-2 mb-2 border-bottom border-dashed">
                        <h6 class="fw-bold mb-0 font-sans text-dark">KOPEG MART BANDUNG</h6>
                        <small class="text-muted d-block">Koperasi Konsumen Pegawai POLMAN Bandung</small>
                        <small class="text-muted d-block">Tanda Terima Pembayaran Cicilan Piutang Konsumsi</small>
                        <div class="d-flex justify-content-between text-muted mt-1 small">
                            <span>No. Bukti: <strong class="text-dark" id="modalReceiptNo">#KAS-REC-202611-042</strong></span>
                            <span id="modalReceiptDate">16 Nov 2026</span>
                        </div>
                    </div>

                    <!-- Info Anggota & Kasir -->
                    <div class="mb-2 pb-2 border-bottom border-dashed">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Kasir Penerima:</span>
                            <span class="fw-bold text-dark">Budi Santoso (01)</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Anggota:</span>
                            <span class="fw-bold text-dark" id="modalReceiptMemberName">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Nomor ID:</span>
                            <span class="fw-bold text-dark" id="modalReceiptMemberId">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Metode Pembayaran:</span>
                            <span class="badge bg-primary" id="modalReceiptPaymentMethod">TUNAI</span>
                        </div>
                        <div class="d-flex justify-content-between d-none" id="modalReceiptTransferRefRow">
                            <span class="text-muted">No. Referensi:</span>
                            <span class="fw-bold text-dark font-monospace" id="modalReceiptTransferRef">-</span>
                        </div>
                    </div>

                    <!-- Rincian Alokasi Faktur -->
                    <div class="mb-2 pb-2 border-bottom border-dashed">
                        <span class="text-muted d-block mb-1 fw-bold">Alokasi Pelunasan Faktur:</span>
                        <div id="modalReceiptAllocationsContainer">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <!-- Total & Rekonsiliasi Plafon -->
                    <div class="mb-2">
                        <div class="d-flex justify-content-between fs-6 fw-bold">
                            <span>TOTAL KAS DITERIMA:</span>
                            <span class="text-success" id="modalReceiptTotalPaid">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted pt-1">
                            <span>Sisa Saldo Piutang:</span>
                            <span class="fw-bold text-dark" id="modalReceiptRemainingDebt">Rp 0</span>
                        </div>
                        <div class="d-flex justify-content-between text-success fw-bold">
                            <span>Sisa Limit Belanja Pulih:</span>
                            <span id="modalReceiptRestoredLimit">Rp 0</span>
                        </div>
                    </div>

                    <!-- Accounting Note PRD AC-09 & FR-08B -->
                    <div class="p-2 rounded bg-white border border-dashed text-secondary mt-2 text-center" style="font-size: 0.7rem;">
                        <strong>Catatan Akuntansi Koperasi:</strong><br>
                        Pembayaran ini dicatat resmi sebagai <strong>KAS MASUK</strong> (Pelunasan Piutang) dan <strong>BUKAN OMZET BARU</strong>.
                    </div>
                </div>

                <div class="alert alert-success py-2 px-3 small d-flex align-items-center gap-2 mb-0">
                    <i class="bi bi-printer-fill fs-5"></i>
                    <span>Tanda terima resmi telah diterbitkan. Limit belanja anggota telah dipulihkan.</span>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 justify-content-between">
                <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1.5" onclick="window.print()">
                    <i class="bi bi-printer"></i>
                    <span>Cetak Tanda Terima</span>
                </button>
                <button type="button" class="btn btn-success btn-sm fw-semibold px-3" data-bs-dismiss="modal" onclick="onCloseReceiptModal()">
                    Selesai & Transaksi Baru
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ==============================================================================
// 1. DATA MASTER & STATE PELAYANAN PIUTANG
// ==============================================================================
const debtorCatalogue = @json($debtorMembers);
let currentMember = null;
let allocationMode = 'auto'; // 'auto' (FIFO) or 'manual'
let invoiceAllocations = {}; // invoice_id -> allocated_amount

function onSelectMember(memberId) {
    const emptyState = document.getElementById('emptyMemberState');
    const content = document.getElementById('memberServiceContent');
    const zeroDebt = document.getElementById('zeroDebtState');
    const debtDetails = document.getElementById('debtDetailsContainer');

    if (!memberId) {
        emptyState.classList.remove('d-none');
        content.classList.add('d-none');
        currentMember = null;
        return;
    }

    currentMember = debtorCatalogue.find(m => m.id === memberId);
    if (!currentMember) return;

    emptyState.classList.add('d-none');
    content.classList.remove('d-none');

    // Populate Member Info Card
    document.getElementById('cardMemberName').textContent = currentMember.name;
    document.getElementById('cardMemberIdBadge').textContent = currentMember.id;
    document.getElementById('cardMemberUnit').textContent = currentMember.unit + ' • Telp: ' + currentMember.phone;

    // Status Badge
    const statusBadge = document.getElementById('cardCreditStatusBadge');
    const overdueAlert = document.getElementById('overdueAlertBanner');

    if (currentMember.has_overdue) {
        statusBadge.className = 'badge bg-danger text-white px-2.5 py-1.5';
        statusBadge.innerHTML = '<i class="bi bi-exclamation-octagon-fill me-1"></i> DIBLOKIR SEMENTARA (OVERDUE)';
        overdueAlert.classList.remove('d-none');
    } else {
        statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5';
        statusBadge.innerHTML = '<i class="bi bi-patch-check-fill me-1"></i> FASILITAS KREDIT AKTIF';
        overdueAlert.classList.add('d-none');
    }

    document.getElementById('cardTotalDebt').textContent = `Rp ${currentMember.total_debt.toLocaleString('id-ID')}`;
    document.getElementById('cardInvoiceCount').textContent = `${currentMember.invoices.length} Faktur Terbuka`;
    document.getElementById('cardAvailableLimit').textContent = `Rp ${currentMember.available_limit.toLocaleString('id-ID')}`;
    document.getElementById('cardEarliestDueDate').textContent = currentMember.earliest_due_date || '-';

    const dueNotice = document.getElementById('cardDueNotice');
    if (currentMember.has_overdue) {
        dueNotice.className = 'd-block text-danger fw-bold';
        dueNotice.textContent = `Lewat ${Math.abs(currentMember.days_remaining)} Hari`;
    } else if (currentMember.days_remaining > 0) {
        dueNotice.className = 'd-block text-warning-emphasis fw-bold';
        dueNotice.textContent = `${currentMember.days_remaining} Hari Lagi`;
    } else {
        dueNotice.className = 'd-block text-muted';
        dueNotice.textContent = 'Tidak Ada Tagihan';
    }

    // Check if zero debt
    if (currentMember.total_debt === 0 || currentMember.invoices.length === 0) {
        zeroDebt.classList.remove('d-none');
        debtDetails.classList.add('d-none');
    } else {
        zeroDebt.classList.add('d-none');
        debtDetails.classList.remove('d-none');

        // Reset input nominal to 0 and render table
        document.getElementById('paymentAmountInput').value = '';
        document.getElementById('paymentAmountInput').max = currentMember.total_debt;
        document.getElementById('tableInvoiceBadge').textContent = `${currentMember.invoices.length} Faktur`;
        document.getElementById('tableTotalBalance').textContent = `Rp ${currentMember.total_debt.toLocaleString('id-ID')}`;

        allocationMode = 'auto';
        document.getElementById('allocAuto').checked = true;
        recalculateFifoAllocation(0);
    }
}

function quickPickMember(id) {
    document.getElementById('memberSelect').value = id;
    onSelectMember(id);
}

// ==============================================================================
// 2. RENDERING TABEL FAKTUR & ALOKASI PEMBAYARAN
// ==============================================================================
function renderInvoicesTable() {
    if (!currentMember) return;
    const tbody = document.getElementById('invoicesTableBody');
    let html = '';

    currentMember.invoices.forEach((inv, index) => {
        let badgeHtml = '';
        if (inv.status === 'OVERDUE') {
            badgeHtml = `<span class="badge bg-danger text-white">OVERDUE</span>`;
        } else if (inv.status === 'PARTIALLY_PAID') {
            badgeHtml = `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">SEBAGIAN</span>`;
        } else {
            badgeHtml = `<span class="badge bg-primary-subtle text-primary border border-primary-subtle">OPEN</span>`;
        }

        const allocated = invoiceAllocations[inv.id] || 0;
        const newBalance = inv.remaining_amount - allocated;

        html += `
            <tr id="invRow-${inv.id}">
                <td>
                    <span class="fw-bold text-dark font-monospace">${inv.id}</span>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">${inv.items_summary}</small>
                </td>
                <td>${inv.pickup_date}</td>
                <td>
                    <span class="${inv.days_left < 0 ? 'text-danger fw-bold' : (inv.days_left <= 2 ? 'text-warning-emphasis fw-bold' : 'text-dark')}">
                        ${inv.due_date}
                    </span>
                    <small class="text-muted d-block" style="font-size: 0.68rem;">
                        ${inv.days_left < 0 ? 'Lewat ' + Math.abs(inv.days_left) + ' hari' : inv.days_left + ' hari lagi'}
                    </small>
                </td>
                <td class="text-end font-monospace">
                    <span class="fw-bold text-dark">Rp ${inv.remaining_amount.toLocaleString('id-ID')}</span>
                    ${allocated > 0 ? `<small class="d-block text-success" style="font-size: 0.7rem;">Sisa: Rp ${newBalance.toLocaleString('id-ID')}</small>` : ''}
                </td>
                <td class="text-center">${badgeHtml}</td>
                <td class="text-end">
                    ${allocationMode === 'auto' ? `
                        <span class="fw-bold font-monospace ${allocated > 0 ? 'text-success' : 'text-muted'}">
                            Rp ${allocated.toLocaleString('id-ID')}
                        </span>
                        ${allocated >= inv.remaining_amount ? '<small class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.62rem;">LUNAS</small>' : ''}
                    ` : `
                        <input type="number" class="form-control form-control-sm text-end font-monospace py-0.5 px-1.5" style="font-size: 0.78rem;" value="${allocated}" max="${inv.remaining_amount}" min="0" oninput="onManualAllocationChange('${inv.id}', this.value)">
                    `}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;

    // Total Allocated Display
    const totalAlloc = Object.values(invoiceAllocations).reduce((sum, v) => sum + v, 0);
    document.getElementById('tableTotalAllocated').textContent = `Rp ${totalAlloc.toLocaleString('id-ID')}`;

    // Update Live Impact Preview
    const currentDebt = currentMember.total_debt;
    const newDebt = Math.max(0, currentDebt - totalAlloc);
    const newLimit = currentMember.available_limit + totalAlloc;

    document.getElementById('previewNewDebt').textContent = `Rp ${newDebt.toLocaleString('id-ID')}`;
    document.getElementById('previewNewLimit').textContent = `Rp ${newLimit.toLocaleString('id-ID')} (+Rp ${totalAlloc.toLocaleString('id-ID')})`;

    validateRepaymentForm();
}

function recalculateFifoAllocation(totalPay) {
    if (!currentMember) return;
    invoiceAllocations = {};
    let unallocated = Math.max(0, totalPay);

    // Urutkan faktur berdasarkan due_date terlama (FIFO)
    currentMember.invoices.forEach(inv => {
        if (unallocated <= 0) {
            invoiceAllocations[inv.id] = 0;
        } else if (unallocated >= inv.remaining_amount) {
            invoiceAllocations[inv.id] = inv.remaining_amount;
            unallocated -= inv.remaining_amount;
        } else {
            invoiceAllocations[inv.id] = unallocated;
            unallocated = 0;
        }
    });

    renderInvoicesTable();
}

function onPaymentAmountInput(val) {
    const pay = parseInt(val, 10) || 0;
    const errEl = document.getElementById('paymentAmountError');

    if (pay < 0 || (currentMember && pay > currentMember.total_debt)) {
        errEl.classList.remove('d-none');
        errEl.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>Nominal tidak boleh melebihi total saldo piutang (Rp ${currentMember.total_debt.toLocaleString('id-ID')}).`;
    } else {
        errEl.classList.add('d-none');
    }

    if (allocationMode === 'auto') {
        recalculateFifoAllocation(pay);
    } else {
        renderInvoicesTable();
    }
}

function setPayAllDebt() {
    if (!currentMember) return;
    document.getElementById('paymentAmountInput').value = currentMember.total_debt;
    onPaymentAmountInput(currentMember.total_debt);
}

function setPayEarliestInvoice() {
    if (!currentMember || currentMember.invoices.length === 0) return;
    const earliest = currentMember.invoices[0];
    document.getElementById('paymentAmountInput').value = earliest.remaining_amount;
    onPaymentAmountInput(earliest.remaining_amount);
}

function setPayAmount(amt) {
    if (!currentMember) return;
    const capped = Math.min(amt, currentMember.total_debt);
    document.getElementById('paymentAmountInput').value = capped;
    onPaymentAmountInput(capped);
}

function toggleAllocationMode(mode) {
    allocationMode = mode;
    const help = document.getElementById('allocModeHelp');
    if (mode === 'auto') {
        help.textContent = "Sistem memprioritaskan pembayaran ke faktur yang memiliki tanggal jatuh tempo terawal terlebih dahulu (FIFO).";
        const pay = parseInt(document.getElementById('paymentAmountInput').value, 10) || 0;
        recalculateFifoAllocation(pay);
    } else {
        help.textContent = "Mode Manual: Anda dapat menyesuaikan alokasi pembayaran rupiah per masing-masing faktur.";
        renderInvoicesTable();
    }
}

function onManualAllocationChange(invId, val) {
    const amt = parseInt(val, 10) || 0;
    const inv = currentMember.invoices.find(i => i.id === invId);
    if (!inv) return;

    invoiceAllocations[invId] = Math.min(amt, inv.remaining_amount);

    // Sum manual
    const totalAlloc = Object.values(invoiceAllocations).reduce((sum, v) => sum + v, 0);
    document.getElementById('paymentAmountInput').value = totalAlloc;
    renderInvoicesTable();
}

// ==============================================================================
// 3. VALIDASI METODE BAYAR & FORM
// ==============================================================================
function onRepaymentMethodChanged(method) {
    const cashBox = document.getElementById('repayCashBox');
    const transferBox = document.getElementById('repayTransferBox');

    if (method === 'CASH') {
        cashBox.classList.remove('d-none');
        transferBox.classList.add('d-none');
    } else {
        cashBox.classList.add('d-none');
        transferBox.classList.remove('d-none');
    }
    validateRepaymentForm();
}

function validateRepaymentForm() {
    const btn = document.getElementById('btnSubmitRepayment');
    if (!currentMember || currentMember.total_debt === 0) {
        btn.disabled = true;
        return;
    }

    const pay = parseInt(document.getElementById('paymentAmountInput').value, 10) || 0;
    const totalAlloc = Object.values(invoiceAllocations).reduce((sum, v) => sum + v, 0);
    const method = document.getElementById('repaymentMethod').value;
    const matchBadge = document.getElementById('allocationMatchBadge');

    // Check allocation match
    if (pay > 0 && pay === totalAlloc) {
        matchBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
        matchBadge.textContent = 'Alokasi Pas';
    } else {
        matchBadge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        matchBadge.textContent = 'Alokasi Belum Pas';
    }

    if (pay <= 0 || pay > currentMember.total_debt || pay !== totalAlloc) {
        btn.disabled = true;
        btn.title = "Nominal pembayaran belum valid atau alokasi belum pas";
        return;
    }

    if (method === 'BANK_TRANSFER') {
        const ref = document.getElementById('repayTransferRef')?.value.trim();
        const checked = document.getElementById('repayTransferCheck')?.checked;
        if (!ref || !checked) {
            btn.disabled = true;
            btn.title = "Wajib isi nomor referensi transfer dan konfirmasi penerimaan dana";
            return;
        }
    }

    btn.disabled = false;
    btn.title = "Klik untuk menyimpan pembayaran cicilan";
}

// ==============================================================================
// 4. STRUK PELUNASAN / CICILAN MODAL (FR-08B & PRD AC-09)
// ==============================================================================
function openRepaymentReceiptModal() {
    const pay = parseInt(document.getElementById('paymentAmountInput').value, 10) || 0;
    if (pay <= 0 || !currentMember) return;

    const method = document.getElementById('repaymentMethod').value;
    const now = new Date();
    const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
    const receiptNo = '#KAS-REC-' + now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + '-' + Math.floor(1000 + Math.random() * 9000);

    document.getElementById('modalReceiptNo').textContent = receiptNo;
    document.getElementById('modalReceiptDate').textContent = dateStr;
    document.getElementById('modalReceiptMemberName').textContent = currentMember.name;
    document.getElementById('modalReceiptMemberId').textContent = currentMember.id + ' (' + currentMember.unit + ')';
    document.getElementById('modalReceiptTotalPaid').textContent = `Rp ${pay.toLocaleString('id-ID')}`;

    const methodBadge = document.getElementById('modalReceiptPaymentMethod');
    const transferRow = document.getElementById('modalReceiptTransferRefRow');

    if (method === 'CASH') {
        methodBadge.className = 'badge bg-success';
        methodBadge.textContent = 'TUNAI (CASH)';
        transferRow.classList.add('d-none');
    } else {
        methodBadge.className = 'badge bg-primary';
        methodBadge.textContent = 'TRANSFER / QRIS';
        transferRow.classList.remove('d-none');
        document.getElementById('modalReceiptTransferRef').textContent = document.getElementById('repayTransferRef').value.trim();
    }

    // Populate Allocations
    const allocContainer = document.getElementById('modalReceiptAllocationsContainer');
    let allocHtml = '';
    currentMember.invoices.forEach(inv => {
        const amt = invoiceAllocations[inv.id] || 0;
        if (amt > 0) {
            const isFull = (amt >= inv.remaining_amount);
            allocHtml += `
                <div class="d-flex justify-content-between mb-1">
                    <span>${inv.id} ${isFull ? '<strong class="text-success">[LUNAS]</strong>' : '<span class="text-warning-emphasis">[SEBAGIAN]</span>'}</span>
                    <span class="fw-bold">Rp ${amt.toLocaleString('id-ID')}</span>
                </div>
            `;
        }
    });
    allocContainer.innerHTML = allocHtml;

    // Remaining debt & restored limit
    const totalAlloc = Object.values(invoiceAllocations).reduce((sum, v) => sum + v, 0);
    const newDebt = Math.max(0, currentMember.total_debt - totalAlloc);
    const newLimit = currentMember.available_limit + totalAlloc;

    document.getElementById('modalReceiptRemainingDebt').textContent = `Rp ${newDebt.toLocaleString('id-ID')}`;
    document.getElementById('modalReceiptRestoredLimit').textContent = `Rp ${newLimit.toLocaleString('id-ID')} (+Rp ${totalAlloc.toLocaleString('id-ID')})`;

    const modal = new bootstrap.Modal(document.getElementById('receiptCicilanModal'));
    modal.show();
}

function onCloseReceiptModal() {
    if (!currentMember) return;
    const totalAlloc = Object.values(invoiceAllocations).reduce((sum, v) => sum + v, 0);

    // Apply payment to mock data in memory
    currentMember.invoices.forEach(inv => {
        const amt = invoiceAllocations[inv.id] || 0;
        if (amt > 0) {
            inv.remaining_amount -= amt;
            inv.paid_amount += amt;
            if (inv.remaining_amount <= 0) {
                inv.status = 'PAID';
            } else {
                inv.status = 'PARTIALLY_PAID';
            }
        }
    });

    // Remove paid invoices
    currentMember.invoices = currentMember.invoices.filter(i => i.remaining_amount > 0);
    currentMember.total_debt = currentMember.invoices.reduce((sum, i) => sum + i.remaining_amount, 0);
    currentMember.available_limit = 1000000 - currentMember.total_debt;
    currentMember.has_overdue = currentMember.invoices.some(i => i.status === 'OVERDUE');

    // Reset inputs
    document.getElementById('repayTransferRef').value = '';
    document.getElementById('repayTransferCheck').checked = false;

    // Refresh view
    onSelectMember(currentMember.id);
}
</script>
@endsection