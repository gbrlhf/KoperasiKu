@extends('layouts.app')

@section('title', 'Impor Data Historis Excel/CSV — KoperasiKu')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h4 class="fw-bold mb-0 text-dark">Impor Data Historis Excel / CSV</h4>
            <span class="badge bg-primary text-white rounded-pill px-2.5 py-1 small">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> FR-03 Idempotent
            </span>
        </div>
        <p class="text-secondary small mb-0">Unggah berkas migrasi massal (Master Produk, Anggota, Stok Awal, Histori Transaksi) dengan validasi baris sebelum commit.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5 shadow-xs" onclick="downloadTemplate()">
            <i class="bi bi-download"></i>
            <span>Unduh Template Excel</span>
        </button>
    </div>
</div>
@endsection

@section('content')

<!-- ATURAN BISNIS BANNER: IDEMPOTENT & VALIDASI BARIS -->
<div class="alert alert-info border-info-subtle rounded-3 p-3 mb-4 shadow-xs">
    <div class="d-flex align-items-start gap-3">
        <div class="p-2 bg-primary text-white rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
            <i class="bi bi-shield-check fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="fw-bold text-primary mb-1">Prinsip Impor Idempotent & Non-Destructive (FR-03)</h6>
            <p class="text-secondary small mb-0 lh-base">
                Sistem menjamin sifat <strong>idempotent</strong>: mengunggah file yang sama lebih dari satu kali tidak akan menduplikasi baris transaksi atau mengubah saldo piutang yang sudah benar. Setiap baris divalidasi terlebih dahulu, dan Manajer dapat meninjau baris error sebelum melakukan commit final.
            </p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- FORM UNGGAH FILE & PILIH JENIS DATA -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                    <i class="bi bi-cloud-arrow-up text-primary me-1.5"></i> Formulir Unggah File Migrasi
                </h5>
                <small class="text-muted" style="font-size: 0.72rem;">Mendukung format file .xlsx, .xls, dan .csv (Maksimal 10 MB)</small>
            </div>
            <div class="card-body p-3 p-md-4">
                <form id="importUploadForm" onsubmit="simulateValidation(event)">
                    
                    <!-- Pilihan Jenis Data -->
                    <div class="mb-3">
                        <label for="importDataType" class="form-label fw-bold text-dark small">Pilih Jenis Entitas Data</label>
                        <select class="form-select" id="importDataType" required>
                            <option value="PRODUK">Master Produk, HPP & Harga Jual</option>
                            <option value="ANGGOTA">Data Anggota Koperasi & Departemen</option>
                            <option value="STOK">Stok Awal Cut-Off & Fisik Gerai</option>
                            <option value="TRANSAKSI">Histori Transaksi Kasir & Piutang</option>
                        </select>
                    </div>

                    <!-- Dropzone Area -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small">Berkas Excel / CSV</label>
                        <div class="border border-2 border-dashed rounded-3 p-4 text-center bg-light" id="dropzoneBox" style="cursor: pointer;" onclick="document.getElementById('fileUploadInput').click()">
                            <i class="bi bi-file-earmark-arrow-up text-primary fs-1 d-block mb-2"></i>
                            <div class="fw-bold text-dark small mb-1" id="fileChosenLabel">Klik atau seret file ke area ini</div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;">Format: .xlsx / .csv &bull; Pastikan kolom sesuai template</small>
                            <input type="file" id="fileUploadInput" class="d-none" accept=".xlsx,.xls,.csv" onchange="handleFileChosen(this)">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm d-inline-flex align-items-center justify-content-center gap-2" id="btnValidateFile">
                        <i class="bi bi-search"></i>
                        <span>Pindai & Validasi Baris Data</span>
                    </button>
                </form>
            </div>
            <div class="card-footer bg-light border-top py-2.5 px-3">
                <small class="text-muted d-flex align-items-center justify-content-between" style="font-size: 0.72rem;">
                    <span>Butuh format kolom baku?</span>
                    <button type="button" class="btn btn-link p-0 text-primary fw-bold text-decoration-none" style="font-size: 0.72rem;" onclick="downloadTemplate()">Unduh Template</button>
                </small>
            </div>
        </div>
    </div>

    <!-- PREVIEW VALIDASI BARIS DATA -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm bg-white h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                        <i class="bi bi-clipboard-check text-primary me-1.5"></i> Hasil Pratinjau Validasi Baris
                    </h5>
                    <small class="text-muted" style="font-size: 0.72rem;">Pemeriksaan integritas skema, foreign key, dan duplikasi data</small>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle small fw-bold">13 Baris Valid</span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle small fw-bold">2 Baris Error</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 280px;">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.78rem;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 50px;">Baris</th>
                                <th>Status</th>
                                <th>Identitas Data</th>
                                <th>Keterangan / Diagnostik Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-bold font-monospace">1</td>
                                <td><span class="badge bg-success text-white">VALID</span></td>
                                <td><strong>SBK-BRS-001</strong> &bull; Beras Premium Ramos 5 Kg</td>
                                <td class="text-muted">Siap di-commit (HPP Rp 61.000)</td>
                            </tr>
                            <tr>
                                <td class="fw-bold font-monospace">2</td>
                                <td><span class="badge bg-success text-white">VALID</span></td>
                                <td><strong>MNY-GRG-002</strong> &bull; Minyak Goreng 2 Liter</td>
                                <td class="text-muted">Siap di-commit (HPP Rp 29.500)</td>
                            </tr>
                            <tr class="table-danger bg-opacity-25">
                                <td class="fw-bold font-monospace text-danger">3</td>
                                <td><span class="badge bg-danger text-white">ERROR</span></td>
                                <td><strong>SBK-GLA-003</strong> &bull; Gula Pasir Putih</td>
                                <td class="text-danger fw-semibold">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Kolom HPP kosong atau bernilai negatif (&le; 0).
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold font-monospace">4</td>
                                <td><span class="badge bg-success text-white">VALID</span></td>
                                <td><strong>SGR-TLR-004</strong> &bull; Telur Ayam Fresh 1 Kg</td>
                                <td class="text-muted">Siap di-commit (HPP Rp 23.500)</td>
                            </tr>
                            <tr class="table-danger bg-opacity-25">
                                <td class="fw-bold font-monospace text-danger">5</td>
                                <td><span class="badge bg-danger text-white">ERROR</span></td>
                                <td><strong>INVALID-SKU-99</strong> &bull; Sabun Batang Mandi</td>
                                <td class="text-danger fw-semibold">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Format SKU tidak sesuai konvensi regex [A-Z]{3}-[A-Z]{3}-[0-9]{3}.
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold font-monospace">6</td>
                                <td><span class="badge bg-success text-white">VALID</span></td>
                                <td><strong>MNM-UHT-005</strong> &bull; Susu UHT 1 Liter</td>
                                <td class="text-muted">Siap di-commit (HPP Rp 15.800)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white border-top py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small class="text-muted" style="font-size: 0.72rem;">
                    Baris error dapat dilewati atau diperbaiki di berkas Excel sebelum commit.
                </small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="alert('Mengabaikan baris error dan menyaring hanya baris valid.')">
                        Abaikan Error
                    </button>
                    <button type="button" class="btn btn-success btn-sm px-3 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5" onclick="openCommitModal()">
                        <i class="bi bi-check2-circle"></i>
                        <span>Commit Impor Idempotent</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. RIWAYAT BERKAS IMPOR SEBELUMNYA -->
<div class="card border-0 shadow-sm bg-white mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="card-title fw-bold text-dark mb-0 fs-6">
                <i class="bi bi-clock-history text-primary me-1.5"></i> Riwayat Unggahan Berkas Migrasi & Impor
            </h5>
            <small class="text-muted" style="font-size: 0.72rem;">Log audit seluruh commit data eksekutif untuk akuntabilitas data</small>
        </div>
        <span class="badge bg-light text-secondary border small">Total {{ count($importHistory) }} Berkas</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
            <thead class="table-light">
                <tr>
                    <th>Batch ID</th>
                    <th>Jenis Entitas</th>
                    <th>Nama Berkas</th>
                    <th>Waktu Unggah</th>
                    <th class="text-center">Total Baris</th>
                    <th class="text-center">Status</th>
                    <th>Aktor</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($importHistory as $ih)
                    <tr>
                        <td class="fw-bold font-monospace text-primary">{{ $ih['id'] }}</td>
                        <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $ih['data_type'] }}</span></td>
                        <td class="fw-semibold text-dark">{{ $ih['filename'] }}</td>
                        <td class="text-muted small">{{ $ih['uploaded_at'] }}</td>
                        <td class="text-center">
                            <span class="fw-bold text-dark">{{ $ih['total_rows'] }}</span>
                            <small class="text-muted d-block" style="font-size: 0.68rem;">{{ $ih['valid_rows'] }} sukses</small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success text-white px-2 py-1">
                                <i class="bi bi-check-all me-1"></i> {{ $ih['status'] }}
                            </span>
                        </td>
                        <td class="text-secondary small">{{ $ih['actor'] }}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-secondary btn-sm px-2.5 py-1 shadow-xs" onclick="alert('Mengunduh log audit impor untuk batch {{ $ih['id'] }}')">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>Log</span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL KONFIRMASI COMMIT IMPOR IDEMPOTENT (#commitImportModal) -->
<div class="modal fade" id="commitImportModal" tabindex="-1" aria-labelledby="commitImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-database-check fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6" id="commitImportModalLabel">Konfirmasi Commit Data Idempotent</h5>
                </div>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 bg-light rounded-3 border mb-3 text-center">
                    <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">Ringkasan Baris Commit:</small>
                    <div class="fs-4 fw-extrabold text-success">13 Baris Siap Disimpan</div>
                    <div class="text-danger small mt-1">2 baris error akan otomatis dilewati</div>
                </div>

                <div class="alert alert-info border-info-subtle small mb-3">
                    <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                    <strong>Jaminan Idempotent (FR-03):</strong> Sistem mencocokkan data berdasarkan kunci unik (SKU/Barcode/ID Anggota). Baris yang sudah pernah ada di database akan diperbarui atributnya tanpa menduplikasi rekaman atau mengubah saldo piutang berjalan.
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success px-4 fw-semibold shadow-sm" onclick="executeCommit()">
                        <i class="bi bi-check-lg me-1"></i> Eksekusi Commit
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.handleFileChosen = function(input) {
    if (input.files && input.files[0]) {
        document.getElementById('fileChosenLabel').textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
    }
};

window.simulateValidation = function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnValidateFile');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> <span>Memvalidasi...</span>';
    setTimeout(() => {
        btn.innerHTML = '<i class="bi bi-search"></i> <span>Pindai & Validasi Baris Data</span>';
        alert('Pemindaian selesai: 13 baris valid ditemukan, 2 baris error dideteksi. Pratinjau diperbarui di kolom kanan.');
    }, 700);
};

window.openCommitModal = function() {
    const modal = new bootstrap.Modal(document.getElementById('commitImportModal'));
    modal.show();
};

window.executeCommit = function() {
    const modalEl = document.getElementById('commitImportModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    setTimeout(() => {
        alert('Commit impor idempotent berhasil diselesaikan! 13 baris data telah resmi masuk ke sistem dan tercatat di Log Audit.');
        location.reload();
    }, 400);
};

window.downloadTemplate = function() {
    const type = document.getElementById('importDataType')?.value || 'PRODUK';
    alert('Mengunduh template berkas Excel resmi untuk jenis: ' + type);
};
</script>
@endsection
