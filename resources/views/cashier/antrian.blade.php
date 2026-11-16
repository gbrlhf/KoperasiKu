@extends('layouts.app')

@section('title', 'Antrian Pickup Pesanan')

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Antrian Pickup & Verifikasi Kasir</h4>
    <p class="text-muted small mb-0">Verifikasi kode pickup anggota, periksa pembayaran tunai/transfer, dan konfirmasi serah terima barang.</p>
</div>
@endsection

@section('content')
<x-card title="Daftar Antrian Pesanan (Placeholder)">
    <p class="text-muted mb-3">
        Daftar pesanan anggota berstatus <code>PENDING_PICKUP</code>. Kasir dapat memverifikasi pembayaran dan mengonfirmasi penyerahan barang dengan tombol anti klik ganda.
    </p>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Data antrian diambil dari <code>GET /api/v1/cashier/orders</code> dan konfirmasi pickup memanggil <code>POST /api/v1/orders/{id}/confirm-pickup</code>.</div>
    </div>
</x-card>
@endsection
