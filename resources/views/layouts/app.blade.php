<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Beranda') — {{ config('koperasi.brand_name', 'KoperasiKu') }}</title>

    <!-- Vite Assets (Bootstrap 5, Chart.js, Icons, Sass) -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-light">

    {{-- Top Navbar --}}
    @include('partials.navbar')

    {{-- Mobile Navigation Pill Bar (untuk layar kecil) --}}
    <div class="d-md-none bg-white border-bottom px-3 py-2 overflow-x-auto">
        @php $role = session('active_role', 'MEMBER'); @endphp
        <div class="d-flex gap-2 text-nowrap">
            @if($role === 'MEMBER')
                <a href="{{ route('member.dashboard') }}" class="btn btn-sm {{ request()->routeIs('member.dashboard') || request()->routeIs('member.index') ? 'btn-primary' : 'btn-light border' }}">Dashboard</a>
                <a href="{{ route('member.katalog') }}" class="btn btn-sm {{ request()->routeIs('member.katalog') ? 'btn-primary' : 'btn-light border' }}">Katalog Sembako</a>
                <a href="{{ route('member.pesanan') }}" class="btn btn-sm {{ request()->routeIs('member.pesanan') ? 'btn-primary' : 'btn-light border' }}">Kode Pickup</a>
                <a href="{{ route('member.kredit') }}" class="btn btn-sm {{ request()->routeIs('member.kredit') ? 'btn-primary' : 'btn-light border' }}">Piutang 7 Hari</a>
            @elseif($role === 'CASHIER')
                <a href="{{ route('cashier.antrian') }}" class="btn btn-sm {{ request()->routeIs('cashier.antrian') ? 'btn-success' : 'btn-light border' }}">Antrian Pickup</a>
                <a href="{{ route('cashier.pos') }}" class="btn btn-sm {{ request()->routeIs('cashier.pos') ? 'btn-success' : 'btn-light border' }}">POS Kasir</a>
                <a href="{{ route('cashier.piutang') }}" class="btn btn-sm {{ request()->routeIs('cashier.piutang') ? 'btn-success' : 'btn-light border' }}">Cicilan</a>
            @elseif($role === 'MANAGER')
                <a href="{{ route('manager.dashboard') }}" class="btn btn-sm {{ request()->routeIs('manager.dashboard') ? 'btn-danger' : 'btn-light border' }}">Dashboard</a>
                <a href="{{ route('manager.piutang') }}" class="btn btn-sm {{ request()->routeIs('manager.piutang') ? 'btn-danger' : 'btn-light border' }}">Monitoring</a>
                <a href="{{ route('manager.kredit') }}" class="btn btn-sm {{ request()->routeIs('manager.kredit') ? 'btn-danger' : 'btn-light border' }}">Kebijakan</a>
                <a href="{{ route('manager.produk') }}" class="btn btn-sm {{ request()->routeIs('manager.produk') ? 'btn-danger' : 'btn-light border' }}">Produk & HPP</a>
                <a href="{{ route('manager.import') }}" class="btn btn-sm {{ request()->routeIs('manager.import') ? 'btn-danger' : 'btn-light border' }}">Impor Data</a>
            @endif
        </div>
    </div>

    {{-- Main Container (Sidebar + Content) --}}
    <div class="container-fluid px-0">
        <div class="d-flex">
            {{-- Desktop Sidebar --}}
            @include('partials.sidebar')

            {{-- Main Content Area --}}
            <main class="main-content flex-grow-1 p-3 p-md-4" style="min-width: 0;">
                <div class="container-fluid px-0">
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
</body>
</html>
