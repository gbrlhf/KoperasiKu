@extends('layouts.app')

@section('title', 'Kebijakan Limit Piutang')

@section('header')
<div>
    <h4 class="fw-bold mb-1 text-dark">Kebijakan Limit Piutang Global</h4>
    <p class="text-muted small mb-0">Pengaturan limit seragam koperasi dan masa jatuh tempo (7 hari kalender).</p>
</div>
@endsection

@section('content')
<x-card title="Pengaturan Limit Global (Placeholder)">
    <p class="text-muted mb-3">
        Kebijakan koperasi konsumsi menerapkan <strong>limit seragam</strong> untuk seluruh anggota aktif. Manajer dapat memperbarui nominal limit global atau mengaktifkan/menonaktifkan fasilitas piutang per anggota.
    </p>

    <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-0" role="alert">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div class="small">Pengaturan limit dibaca dari <code>GET /api/v1/credit-policy</code> dan diperbarui via <code>PATCH /api/v1/credit-policy</code>.</div>
    </div>
</x-card>
@endsection
