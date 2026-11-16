@extends('layouts.app')

@section('title', 'Keranjang Belanja')

@section('header')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark">Keranjang & Checkout</h4>
        <p class="text-muted small mb-0">Periksa barang belanjaan sebelum membuat pesanan pickup.</p>
    </div>
    <div>
        <a href="{{ route('member.katalog') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Katalog</span>
        </a>
    </div>
</div>
@endsection

@section('content')
<x-card title="Rincian Keranjang (Placeholder)">
    <p class="text-muted mb-3">
        Form checkout dengan pilihan pembayaran: <strong>Tunai (CASH)</strong>, <strong>Transfer (BANK_TRANSFER)</strong>, atau <strong>Piutang Anggota (CREDIT)</strong>.
    </p>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Checkout akan mengirim request ke <code>POST /api/v1/orders</code> dengan idempotency key. Opsi piutang akan dicek eligibility-nya via backend.</div>
    </div>
</x-card>
@endsection
