@extends('layouts.app')

@section('title', 'Pelayanan Cicilan Piutang')

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Pelayanan Cicilan & Pelunasan Piutang</h4>
    <p class="text-muted small mb-0">Cari data anggota dan catat pembayaran cicilan tunai atau transfer.</p>
</div>
@endsection

@section('content')
<x-card title="Form Penerimaan Cicilan (Placeholder)">
    <p class="text-muted mb-3">
        Kasir mencari anggota yang sedang dilayani, melihat faktur tagihan yang belum lunas, lalu memasukkan nominal pembayaran dan alokasi faktur.
    </p>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Pencarian tagihan via <code>GET /api/v1/cashier/receivables?member_id=...</code> dan pencatatan cicilan via <code>POST /api/v1/receivables/payments</code>.</div>
    </div>
</x-card>
@endsection
