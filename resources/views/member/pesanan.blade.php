@extends('layouts.app')

@section('title', 'Pesanan & Kode Pickup')

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Pesanan & Kode Pickup Saya</h4>
    <p class="text-muted small mb-0">Pantau status pesanan dan tunjukkan <strong>Kode Pickup</strong> ke kasir saat mengambil barang di koperasi.</p>
</div>
@endsection

@section('content')
<x-card title="Riwayat & Status Pesanan (Placeholder)">
    <p class="text-muted mb-3">
        Daftar pesanan aktif dan selesai. Status pesanan menggunakan badge:
    </p>

    <div class="d-flex gap-2 flex-wrap mb-4">
        <x-badge-status status="PENDING_PICKUP" />
        <x-badge-status status="COMPLETED" />
        <x-badge-status status="CANCELLED" />
    </div>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Data pesanan diambil dari endpoint <code>GET /api/v1/orders/my</code>.</div>
    </div>
</x-card>
@endsection
