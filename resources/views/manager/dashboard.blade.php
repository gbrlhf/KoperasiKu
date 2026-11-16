@extends('layouts.app')

@section('title', 'Dashboard Eksekutif')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Dashboard Eksekutif Manajer</h4>
        <p class="text-muted small mb-0">Ringkasan KPI operasional, omzet, laba kotor, kas masuk, dan prediksi analitik.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-secondary border px-3 py-2 small">
            <i class="bi bi-clock-history me-1"></i> Data Mart: <span class="fw-semibold text-dark">Sinkron</span>
        </span>
    </div>
</div>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <x-card class="border-start border-4 border-primary">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Omzet Penjualan</small>
            <h4 class="fw-bold text-dark mt-2 mb-0">-</h4>
            <small class="text-muted">Dari transaksi COMPLETED</small>
        </x-card>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-card class="border-start border-4 border-success">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Laba Kotor</small>
            <h4 class="fw-bold text-dark mt-2 mb-0">-</h4>
            <small class="text-muted">Omzet bersih - HPP</small>
        </x-card>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-card class="border-start border-4 border-info">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Kas Masuk</small>
            <h4 class="fw-bold text-dark mt-2 mb-0">-</h4>
            <small class="text-muted">Pembayaran terverifikasi</small>
        </x-card>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-card class="border-start border-4 border-warning">
            <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.75rem;">Piutang Terbuka</small>
            <h4 class="fw-bold text-dark mt-2 mb-0">-</h4>
            <small class="text-muted">Snapshot posisi berjalan</small>
        </x-card>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <x-card title="Grafik Tren Omzet vs Laba (Chart.js)">
            <p class="text-muted mb-0">Placeholder kanvas grafik Chart.js untuk visualisasi tren finansial per periode (harian, mingguan, bulanan, tahunan).</p>
        </x-card>
    </div>
    <div class="col-12 col-lg-4">
        <x-card title="Prediksi Permintaan (Forecast 7/30 Hari)">
            <p class="text-muted mb-0">Placeholder rekomendasi restock dan tren permintaan hasil pipeline data mart PIC D/E.</p>
        </x-card>
    </div>
</div>
@endsection
