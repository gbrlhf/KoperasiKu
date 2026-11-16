@php
    $currentRole = session('active_role', 'MEMBER');
@endphp

<div class="sidebar p-3 d-none d-md-block">
    <div class="mb-3 px-2">
        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">
            Menu {{ match($currentRole) {
                'CASHIER' => 'Petugas / Kasir',
                'MANAGER' => 'Manajer',
                default => 'Anggota Koperasi',
            } }}
        </small>
    </div>

    <ul class="nav nav-pills flex-column">
        @if($currentRole === 'MEMBER')
            <li class="nav-item">
                <a href="{{ route('member.katalog') }}" class="nav-link {{ request()->routeIs('member.katalog') ? 'active' : '' }}">
                    <i class="bi bi-grid fs-5"></i>
                    <span>Katalog Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('member.keranjang') }}" class="nav-link {{ request()->routeIs('member.keranjang') ? 'active' : '' }}">
                    <i class="bi bi-cart3 fs-5"></i>
                    <span>Keranjang</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('member.pesanan') }}" class="nav-link {{ request()->routeIs('member.pesanan') ? 'active' : '' }}">
                    <i class="bi bi-bag-check fs-5"></i>
                    <span>Pesanan & Pickup</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('member.kredit') }}" class="nav-link {{ request()->routeIs('member.kredit') ? 'active' : '' }}">
                    <i class="bi bi-credit-card-2-front fs-5"></i>
                    <span>Limit & Tagihan</span>
                </a>
            </li>
        @elseif($currentRole === 'CASHIER')
            <li class="nav-item">
                <a href="{{ route('cashier.antrian') }}" class="nav-link {{ request()->routeIs('cashier.antrian') ? 'active' : '' }}">
                    <i class="bi bi-inbox fs-5"></i>
                    <span>Antrian Pickup</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cashier.pos') }}" class="nav-link {{ request()->routeIs('cashier.pos') ? 'active' : '' }}">
                    <i class="bi bi-upc-scan fs-5"></i>
                    <span>POS Kasir & Barcode</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cashier.piutang') }}" class="nav-link {{ request()->routeIs('cashier.piutang') ? 'active' : '' }}">
                    <i class="bi bi-cash-coin fs-5"></i>
                    <span>Cicilan & Piutang</span>
                </a>
            </li>
        @elseif($currentRole === 'MANAGER')
            <li class="nav-item">
                <a href="{{ route('manager.dashboard') }}" class="nav-link {{ request()->routeIs('manager.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 fs-5"></i>
                    <span>Dashboard Eksekutif</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.kredit') }}" class="nav-link {{ request()->routeIs('manager.kredit') ? 'active' : '' }}">
                    <i class="bi bi-sliders fs-5"></i>
                    <span>Kebijakan Limit</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.piutang') }}" class="nav-link {{ request()->routeIs('manager.piutang') ? 'active' : '' }}">
                    <i class="bi bi-graph-up-arrow fs-5"></i>
                    <span>Monitoring Piutang</span>
                </a>
            </li>
        @endif
    </ul>

    <!-- Quick role badge reminder in sidebar -->
    <div class="mt-4 pt-3 border-top px-2">
        <div class="p-2.5 rounded-3 bg-light border">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-secondary-subtle text-secondary small">Konteks Aktif</span>
            </div>
            <div class="small fw-semibold text-dark">
                {{ match($currentRole) {
                    'CASHIER' => 'Kasir (CASHIER)',
                    'MANAGER' => 'Manajer (MANAGER)',
                    default => 'Anggota (MEMBER)',
                } }}
            </div>
            <p class="text-muted mb-0" style="font-size: 0.75rem;">Ganti peran via tombol di navbar atas.</p>
        </div>
    </div>
</div>
