@php
    $currentRole = $uiRole ?? session('active_role', 'MEMBER');
@endphp

<aside class="sidebar p-0 d-none d-md-block sidebar-{{ strtolower($currentRole) }}" aria-label="Navigasi Portal">
    <div class="sidebar-sticky-inner position-sticky p-3" style="top: 80px; max-height: calc(100vh - 96px); overflow-y: auto; scrollbar-width: thin;">
    <div class="mb-3 px-2">
        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">
            Portal {{ match($currentRole) {
                'CASHIER' => 'Petugas Kasir',
                'MANAGER' => 'Manajer',
                default => 'Pelanggan / Anggota',
            } }}
        </small>
    </div>

    <ul class="nav nav-pills flex-column">
        @if($currentRole === 'MEMBER')
            <li class="nav-item">
                <a href="{{ route('member.dashboard') }}" class="nav-link {{ request()->routeIs('member.dashboard') || request()->routeIs('member.index') ? 'active' : '' }}">
                    <i class="bi bi-person-badge fs-5 me-2"></i>
                    <span>Dashboard Anggota</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('member.katalog') }}" class="nav-link {{ request()->routeIs('member.katalog') ? 'active' : '' }}">
                    <i class="bi bi-bag-heart fs-5 me-2"></i>
                    <span>Katalog Sembako</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('member.pesanan') }}" class="nav-link {{ request()->routeIs('member.pesanan') ? 'active' : '' }}">
                    <i class="bi bi-qr-code-scan fs-5 me-2"></i>
                    <span>Pesanan & Kode Pickup</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('member.kredit') }}" class="nav-link {{ request()->routeIs('member.kredit') ? 'active' : '' }}">
                    <i class="bi bi-wallet2 fs-5 me-2"></i>
                    <span>Limit & Piutang 7 Hari</span>
                </a>
            </li>
        @elseif($currentRole === 'CASHIER')
            <li class="nav-item">
                <a href="{{ route('cashier.pos') }}" class="nav-link {{ (request()->routeIs('cashier.pos') || request()->routeIs('cashier.index')) ? 'active' : '' }}">
                    <i class="bi bi-upc-scan fs-5 me-1"></i>
                    <span>POS Kasir Langsung</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cashier.stok') }}" class="nav-link {{ request()->routeIs('cashier.stok') ? 'active' : '' }}">
                    <i class="bi bi-boxes fs-5 me-1"></i>
                    <span>Monitoring Stok Barang</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cashier.antrian') }}" class="nav-link {{ request()->routeIs('cashier.antrian') ? 'active' : '' }}">
                    <i class="bi bi-qr-code-scan fs-5 me-1"></i>
                    <span>Antrian Pickup Pesanan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('cashier.piutang') }}" class="nav-link {{ request()->routeIs('cashier.piutang') ? 'active' : '' }}">
                    <i class="bi bi-wallet2 fs-5 me-1"></i>
                    <span>Pelayanan Cicilan Piutang</span>
                </a>
            </li>
        @elseif($currentRole === 'MANAGER')
            <li class="nav-item">
                <a href="{{ route('manager.dashboard') }}" data-mgr-page="home" class="nav-link {{ request()->routeIs('manager.dashboard') || request()->routeIs('manager.index') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 fs-5 me-1"></i>
                    <span>Beranda &amp; Ringkasan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.transaksi') }}" data-mgr-page="ops" class="nav-link {{ request()->routeIs('manager.transaksi') ? 'active' : '' }}">
                    <i class="bi bi-receipt fs-5 me-1"></i>
                    <span>Transaksi &amp; Operasional</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.piutang') }}" data-mgr-page="ar" class="nav-link {{ request()->routeIs('manager.piutang') || request()->routeIs('manager.kredit') ? 'active' : '' }}">
                    <i class="bi bi-wallet2 fs-5 me-1"></i>
                    <span>Piutang &amp; Kebijakan Kredit</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.produk') }}" data-mgr-page="data" class="nav-link {{ request()->routeIs('manager.produk') || request()->routeIs('manager.import') ? 'active' : '' }}">
                    <i class="bi bi-boxes fs-5 me-1"></i>
                    <span>Master Data &amp; Stok</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.laporan') }}" data-mgr-page="rep" class="nav-link {{ request()->routeIs('manager.laporan') ? 'active' : '' }}">
                    <i class="bi bi-graph-up-arrow fs-5 me-1"></i>
                    <span>Laporan &amp; Analitik</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.sistem') }}" data-mgr-page="sys" class="nav-link {{ request()->routeIs('manager.sistem') ? 'active' : '' }}">
                    <i class="bi bi-gear fs-5 me-1"></i>
                    <span>Sistem &amp; Audit Log</span>
                </a>
            </li>
        @endif
    </ul>
    </div>
</aside>
