<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') - {{ config('koperasi.brand_name', 'KoperasiKu') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">

    <!-- Vite Assets (Bootstrap 5, Chart.js, Icons, Sass) -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
<link rel="stylesheet" href="{{ asset('css/usability.css') }}?v=20261119-2">
<style>
    @keyframes appMainFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .app-page-enter {
        animation: appMainFadeIn 180ms ease-out;
    }
    @media (prefers-reduced-motion: reduce) {
        .app-page-enter { animation: none; }
    }
</style>
</head>
@php $uiRole = request()->is('cashier*') ? 'CASHIER' : (request()->is('manager*') ? 'MANAGER' : session('active_role', 'MEMBER')); @endphp
<body class="ui-polished bg-light role-{{ strtolower($uiRole) }} d-flex flex-column min-vh-100">

<a class="skip-content" href="#main-content">Lewati ke konten</a>
{{-- Top Navbar --}}
    @include('partials.navbar')

    {{-- Mobile Navigation Pill Bar (untuk layar kecil) --}}
    <div class="mobile-role-nav d-md-none bg-white border-bottom px-3 py-2 overflow-x-auto">
        @php $role = $uiRole; @endphp
        <div class="d-flex gap-2 text-nowrap">
            @if($role === 'MEMBER')
                <a href="{{ route('member.dashboard') }}" class="btn btn-sm {{ request()->routeIs('member.dashboard') || request()->routeIs('member.index') ? 'btn-primary' : 'btn-light border' }}">Dashboard</a>
                <a href="{{ route('member.katalog') }}" class="btn btn-sm {{ request()->routeIs('member.katalog') ? 'btn-primary' : 'btn-light border' }}">Katalog Sembako</a>
                <a href="{{ route('member.pesanan') }}" class="btn btn-sm {{ request()->routeIs('member.pesanan') ? 'btn-primary' : 'btn-light border' }}">Kode Pickup</a>
                <a href="{{ route('member.kredit') }}" class="btn btn-sm {{ request()->routeIs('member.kredit') ? 'btn-primary' : 'btn-light border' }}">Piutang 7 Hari</a>
            @elseif($role === 'CASHIER')
                <a href="{{ route('cashier.pos') }}" class="btn btn-sm {{ (request()->routeIs('cashier.pos') || request()->routeIs('cashier.index')) ? 'btn-success' : 'btn-light border' }}">POS Kasir</a>
                <a href="{{ route('cashier.stok') }}" class="btn btn-sm {{ request()->routeIs('cashier.stok') ? 'btn-success' : 'btn-light border' }}">Stok Barang</a>
                <a href="{{ route('cashier.antrian') }}" class="btn btn-sm {{ request()->routeIs('cashier.antrian') ? 'btn-success' : 'btn-light border' }}">Antrian Pickup</a>
                <a href="{{ route('cashier.piutang') }}" class="btn btn-sm {{ request()->routeIs('cashier.piutang') ? 'btn-success' : 'btn-light border' }}">Cicilan</a>
            @elseif($role === 'MANAGER')
                <a href="{{ route('manager.dashboard') }}" data-mgr-page="home" class="btn btn-sm {{ request()->routeIs('manager.dashboard') || request()->routeIs('manager.index') ? 'btn-success' : 'btn-light border' }}">Beranda</a>
                <a href="{{ route('manager.transaksi') }}" data-mgr-page="ops" class="btn btn-sm {{ request()->routeIs('manager.transaksi') ? 'btn-success' : 'btn-light border' }}">Transaksi</a>
                <a href="{{ route('manager.piutang') }}" data-mgr-page="ar" class="btn btn-sm {{ request()->routeIs('manager.piutang') || request()->routeIs('manager.kredit') ? 'btn-success' : 'btn-light border' }}">Piutang &amp; Kredit</a>
                <a href="{{ route('manager.produk') }}" data-mgr-page="data" class="btn btn-sm {{ request()->routeIs('manager.produk') || request()->routeIs('manager.import') ? 'btn-success' : 'btn-light border' }}">Master &amp; Stok</a>
                <a href="{{ route('manager.laporan') }}" data-mgr-page="rep" class="btn btn-sm {{ request()->routeIs('manager.laporan') ? 'btn-success' : 'btn-light border' }}">Laporan</a>
                <a href="{{ route('manager.sistem') }}" data-mgr-page="sys" class="btn btn-sm {{ request()->routeIs('manager.sistem') ? 'btn-success' : 'btn-light border' }}">Sistem</a>
            @endif
        </div>
    </div>

    {{-- Main Container (Sidebar + Content) --}}
    <div class="container-fluid px-0 w-100 flex-grow-1 d-flex flex-column">
        <div class="d-flex flex-grow-1 w-100 align-items-stretch">
            {{-- Desktop Sidebar --}}
            @include('partials.sidebar')

            {{-- Main Content Area --}}
            <main id="main-content" tabindex="-1" class="main-content app-page-enter flex-grow-1 d-flex flex-column pt-4 pb-5 px-4" style="min-width: 0;">
                <div class="container-fluid px-0 w-100 flex-grow-1">
                    {{-- Flash Alert Messages --}}
                    @include('partials.flash-alert')

                    {{-- Page Header / Breadcrumbs (jika ada) --}}
                    @hasSection('header')
                        <div class="mb-4">
                            @yield('header')
                        </div>
                    @endif

                    {{-- Main Page Content --}}
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Footer --}}
    @include('partials.footer')

    @stack('scripts')
<script src="{{ asset('js/usability.js') }}?v=20261118-7" defer></script>
</body>
</html>
