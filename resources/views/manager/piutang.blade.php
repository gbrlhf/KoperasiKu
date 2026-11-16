@extends('layouts.app')

@section('title', 'Monitoring Piutang & Aging')

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Monitoring Piutang & Aging Debt</h4>
    <p class="text-muted small mb-0">Pengawasan saldo piutang terbuka, daftar anggota dengan tunggakan, dan pengelompokan usia tagihan.</p>
</div>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <x-card title="Distribusi Usia Piutang (Aging)">
            <p class="text-muted mb-0">
                Visualisasi pengelompokan piutang: Lancar (Current), 1–30 hari, 31–60 hari, 61–90 hari, dan >90 hari keterlambatan.
            </p>
        </x-card>
    </div>
    <div class="col-12 col-lg-4">
        <x-card title="Status Pembatasan Kredit (Credit Hold)">
            <p class="text-muted mb-0">
                Anggota yang memiliki tagihan lewat jatuh tempo akan otomatis terkena credit hold oleh aturan backend.
            </p>
        </x-card>
    </div>
</div>

<x-card title="Daftar Tagihan Piutang Terbuka (Placeholder)">
    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Data piutang manajer diambil dari <code>GET /api/v1/manager/receivables</code> dan <code>GET /api/v1/manager/receivables/aging</code>.</div>
    </div>
</x-card>
@endsection
