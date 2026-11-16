@extends('layouts.app')

@section('title', 'POS Kasir & Scanner Barcode')

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Point of Sale (POS) & Scanner Barcode</h4>
    <p class="text-muted small mb-0">Pembelian langsung di meja kasir dengan pemindai barcode kamera HP/webcam atau input SKU manual.</p>
</div>
@endsection

@section('content')
<x-card title="Antarmuka Transaksi Kasir (Placeholder)">
    <p class="text-muted mb-3">
        Modul scanner barcode menggunakan kamera browser (EAN-13, EAN-8, Code128) serta fallback pencarian barang manual.
    </p>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Pencarian produk via scan menggunakan <code>GET /api/v1/products/lookup?barcode=...</code> dan checkout kasir menggunakan <code>POST /api/v1/pos/sales</code>.</div>
    </div>
</x-card>
@endsection
