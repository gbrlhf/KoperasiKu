@props([
    'code' => null,
    'message' => null,
    'dismissible' => true,
])

@php
    $errorCode = strtoupper(trim($code ?? ''));
    
    $messages = [
        'INSUFFICIENT_STOCK' => [
            'title' => 'Stok Tidak Mencukupi',
            'desc' => 'Stok produk yang tersedia saat ini tidak mencukupi untuk memenuhi jumlah pesanan Anda.',
            'icon' => 'bi-box-seam',
        ],
        'CREDIT_NOT_ELIGIBLE' => [
            'title' => 'Fasilitas Piutang Tidak Aktif',
            'desc' => 'Akun Anda belum memenuhi syarat untuk bertransaksi dengan metode piutang/kredit.',
            'icon' => 'bi-shield-slash',
        ],
        'CREDIT_LIMIT_EXCEEDED' => [
            'title' => 'Limit Kredit Terlampaui',
            'desc' => 'Nominal transaksi ini melebihi sisa limit kredit anggota yang tersedia saat ini.',
            'icon' => 'bi-credit-card-2-front',
        ],
        'OVERDUE_RECEIVABLE' => [
            'title' => 'Terdapat Tagihan Lewat Jatuh Tempo',
            'desc' => 'Terdapat tagihan piutang sebelumnya yang melewati 7 hari jatuh tempo. Harap lakukan pembayaran atau cicilan terlebih dahulu di kasir.',
            'icon' => 'bi-calendar-x',
        ],
        'INVALID_PAYMENT' => [
            'title' => 'Pembayaran Tidak Valid',
            'desc' => 'Rincian nominal pembayaran atau alokasi cicilan tidak sesuai dengan faktur.',
            'icon' => 'bi-exclamation-triangle',
        ],
        'DUPLICATE_PAYMENT' => [
            'title' => 'Duplikasi Pembayaran Terdeteksi',
            'desc' => 'Transaksi atau verifikasi pembayaran ini sudah pernah diproses sebelumnya (idempotency safety).',
            'icon' => 'bi-arrow-repeat',
        ],
        'ORDER_ALREADY_COMPLETED' => [
            'title' => 'Pesanan Sudah Selesai',
            'desc' => 'Barang pada pesanan ini telah diserahkan sebelumnya dan statusnya telah selesai.',
            'icon' => 'bi-check2-circle',
        ],
        'FORBIDDEN' => [
            'title' => 'Akses Ditolak',
            'desc' => 'Anda tidak memiliki hak akses yang diizinkan untuk melihat atau melakukan tindakan pada data ini.',
            'icon' => 'bi-shield-lock',
        ],
    ];

    $errorDetail = $messages[$errorCode] ?? [
        'title' => 'Terjadi Kesalahan',
        'desc' => $message ?: 'Permintaan tidak dapat diproses oleh sistem backend.',
        'icon' => 'bi-exclamation-octagon',
    ];

    $finalDesc = $message ?: $errorDetail['desc'];
@endphp

<div {{ $attributes->merge(['class' => 'alert alert-danger d-flex align-items-start gap-3 shadow-sm border-0 border-start border-4 border-danger']) }} role="alert">
    <i class="bi {{ $errorDetail['icon'] }} fs-4 text-danger flex-shrink-0 mt-1"></i>
    <div class="flex-grow-1">
        <h6 class="alert-heading fw-bold mb-1">{{ $errorDetail['title'] }}</h6>
        <p class="mb-0 small text-danger-emphasis">{{ $finalDesc }}</p>
        @if($errorCode && isset($messages[$errorCode]))
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-2 small font-monospace">Kode: {{ $errorCode }}</span>
        @endif
    </div>
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
