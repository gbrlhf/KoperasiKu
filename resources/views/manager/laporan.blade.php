@extends('layouts.app')
@section('title','Laporan Keuangan')
@section('header')
<div><h4 class="fw-bold mb-1">Laporan Keuangan & Analitik</h4>
<p class="text-muted small mb-0">Periode menggunakan waktu Jakarta. Saldo piutang dihitung pada akhir periode.</p></div>
@endsection
@section('content')
<form class="row g-2 align-items-end mb-4" method="GET" action="{{ route('manager.laporan') }}">
    <div class="col-auto"><label class="form-label" for="start">Mulai</label><input id="start" class="form-control" type="date" name="start" value="{{ $start }}" required></div>
    <div class="col-auto"><label class="form-label" for="end">Akhir</label><input id="end" class="form-control" type="date" name="end" value="{{ $end }}" required></div>
    <div class="col-auto"><button type="submit" class="btn btn-primary">Tampilkan</button></div>
</form>
@if(!$data || !$data['ready'])
    <div class="alert alert-info">Data laporan belum tersedia. Silakan periksa kembali setelah sinkronisasi.</div>
@else
    @if($data['is_stale'])<div class="alert alert-warning">Data belum mutakhir. Periksa waktu pembaruan laporan.</div>@endif
    @if(!$data['data_quality']['cogs_complete'] || !$data['data_quality']['expenses_complete'])
        <div class="alert alert-light border">HPP atau biaya belum lengkap. Nilai laba yang tersedia merupakan estimasi.</div>
    @endif
    <div class="d-flex gap-2 mb-3 flex-wrap">
        @foreach(['xlsx'=>'Excel XLSX','pdf'=>'PDF','csv'=>'CSV'] as $format=>$label)
            <a class="btn btn-outline-primary btn-sm" href="{{ url('/api/v1/reports/financial/export') }}?start={{ $start }}&amp;end={{ $end }}&amp;format={{ $format }}">Unduh {{ $label }}</a>
        @endforeach
    </div>
    <x-card title="Ringkasan {{ $start }} — {{ $end }}">
        <div class="table-responsive"><table class="table table-striped">
            @foreach([
              'Omzet penjualan selesai'=>'sales_revenue', 'HPP terjual'=>'cogs',
              'Laba kotor'=>'gross_profit', 'Biaya operasional'=>'operating_expenses',
              'Estimasi laba bersih'=>'estimated_net_profit', 'Kas masuk terverifikasi'=>'cash_receipts',
              'Piutang baru'=>'new_credit_sales', 'Piutang pada akhir periode'=>'receivables_outstanding_as_of_end',
              'Piutang overdue pada akhir periode'=>'overdue_outstanding_as_of_end'
            ] as $label=>$key)
                <tr><th>{{ $label }}</th><td class="text-end">{{ $data['metrics'][$key]===null?'Data belum tersedia':'Rp '.number_format((float)$data['metrics'][$key],0,',','.') }}</td></tr>
            @endforeach
        </table></div>
        <p class="small text-muted mb-0">Terakhir diperbarui {{ $data['last_updated_at'] ?? 'belum ada' }}.</p>
    </x-card>
    <div class="mt-3">
        <x-card title="Produk Terlaris dalam Periode">
            <div class="table-responsive"><table class="table table-sm">
                <thead><tr><th>SKU</th><th>Produk</th><th>Unit</th><th>Omzet</th><th>Laba Kotor</th></tr></thead>
                @forelse($top as $p)
                    <tr><td>{{ $p['sku'] }}</td><td>{{ $p['name'] }}</td><td>{{ $p['units'] }}</td><td>{{ $p['sales_revenue'] }}</td><td>{{ $p['gross_profit']??'HPP tidak lengkap' }}</td></tr>
                @empty <tr><td colspan="5">Belum ada transaksi selesai.</td></tr>
                @endforelse
            </table></div>
        </x-card>
    </div>
@endif
@endsection
