@extends('layouts.app')

@section('title', 'Katalog Produk')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Katalog Produk Koperasi</h4>
        <p class="text-muted small mb-0">Daftar barang konsumsi tersedia. Pemesanan adalah sistem <strong>Ambil Langsung di Koperasi (Pickup Only)</strong>.</p>
    </div>
    <div>
        <a href="{{ route('member.keranjang') }}" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-cart3"></i>
            <span>Lihat Keranjang</span>
        </a>
    </div>
</div>
@endsection

@section('content')
<x-card title="Daftar Barang (Placeholder)">
    <p class="text-muted mb-3">
        Kerangka katalog produk anggota. Menampilkan produk, kategori, harga jual, dan stok <code>available</code> (stok fisik dikurangi pesanan yang belum diambil).
    </p>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Data produk nantinya akan diambil dari endpoint <code>GET /api/v1/products</code>.</div>
    </div>
</x-card>
@endsection
