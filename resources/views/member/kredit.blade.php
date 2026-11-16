@extends('layouts.app')

@section('title', 'Limit & Tagihan Piutang')

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Limit & Tagihan Piutang Anggota</h4>
    <p class="text-muted small mb-0">Informasi fasilitas piutang pembelian barang konsumsi, saldo berjalan, dan riwayat tagihan.</p>
</div>
@endsection

@section('content')
<x-card title="Fasilitas Piutang Saya (Placeholder)">
    <p class="text-muted mb-3">
        Menampilkan ringkasan limit global koperasi, saldo berjalan (outstanding), saldo tertahan (hold), dan sisa limit tersedia.
        <em>(Semua saldo diambil langsung dari respons API, dilarang dihitung di frontend).</em>
    </p>

    <div class="d-flex gap-2 flex-wrap mb-4">
        <x-badge-status status="UNPAID" />
        <x-badge-status status="PARTIALLY_PAID" />
        <x-badge-status status="PAID" />
        <x-badge-status status="OVERDUE" />
    </div>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Data limit diambil dari <code>GET /api/v1/members/me/credit</code> dan tagihan dari <code>GET /api/v1/members/me/receivables</code>.</div>
    </div>
</x-card>
@endsection
