@php
    $currentRole = $uiRole ?? session('active_role', 'MEMBER');
@endphp

<div class="sidebar p-3 d-none d-md-block">
    <div class="mb-3 px-2">
        <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem; letter-spacing: 0.05em;">
            Portal {{ match($currentRole) {
                'CASHIER' => 'Petugas Kasir',
                'MANAGER' => 'Manajer Eksekutif',
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
                <a href="{{ route('cashier.pos') }}" class="nav-link {{ request()->routeIs('cashier.pos') || request()->routeIs('cashier.index') ? 'active' : '' }}">
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
                <a href="{{ route('manager.dashboard') }}" class="nav-link {{ request()->routeIs('manager.dashboard') || request()->routeIs('manager.index') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 fs-5 me-1"></i>
                    <span>Dashboard Eksekutif</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.piutang') }}" class="nav-link {{ request()->routeIs('manager.piutang') ? 'active' : '' }}">
                    <i class="bi bi-graph-up-arrow fs-5 me-1"></i>
                    <span>Monitoring Risiko Piutang</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.kredit') }}" class="nav-link {{ request()->routeIs('manager.kredit') ? 'active' : '' }}">
                    <i class="bi bi-sliders fs-5 me-1"></i>
                    <span>Kebijakan Limit Kredit</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.produk') }}" class="nav-link {{ request()->routeIs('manager.produk') ? 'active' : '' }}">
                    <i class="bi bi-tags fs-5 me-1"></i>
                    <span>Master Produk & HPP</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('manager.import') }}" class="nav-link {{ request()->routeIs('manager.import') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-spreadsheet fs-5 me-1"></i>
                    <span>Impor Data Excel/CSV</span>
                </a>
            </li>
        @endif
    </ul>

    <!-- Quick role badge reminder in sidebar -->
    <div class="mt-4 pt-3 border-top px-2">
        <div class="p-3 rounded-3 bg-light border">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-secondary-subtle text-secondary small">Konteks Aktif</span>
            </div>
            <div class="small fw-bold text-dark">
                {{ match($currentRole) {
                    'CASHIER' => 'Kasir / Petugas (CASHIER)',
                    'MANAGER' => 'Manajer (MANAGER)',
                    default => 'Pelanggan / Anggota (MEMBER)',
                } }}
            </div>
            <p class="text-muted mb-0 mt-1" style="font-size: 0.72rem;">Ganti halaman via menu di pojok kanan atas.</p>
        </div>
    </div>
</div>
