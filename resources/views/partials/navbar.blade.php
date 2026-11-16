@php
    $currentRole = session('active_role', 'MEMBER');
    $availableRoles = session('available_roles', ['MEMBER', 'CASHIER', 'MANAGER']);

    $roleBadges = [
        'MEMBER' => ['label' => 'Anggota', 'badge' => 'bg-primary-subtle text-primary border border-primary-subtle', 'icon' => 'bi-person-badge'],
        'CASHIER' => ['label' => 'Kasir / Petugas', 'badge' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'bi-shop'],
        'MANAGER' => ['label' => 'Manajer', 'badge' => 'bg-danger-subtle text-danger border border-danger-subtle', 'icon' => 'bi-briefcase'],
    ];

    $activeInfo = $roleBadges[$currentRole] ?? $roleBadges['MEMBER'];
@endphp

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top border-bottom border-secondary-subtle py-2">
    <div class="container-fluid px-3 px-lg-4">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white fs-5" href="{{ url('/') }}">
            <div class="bg-primary text-white rounded-3 p-1 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                <i class="bi bi-intersect fs-6"></i>
            </div>
            <span>KOPERADATA</span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <!-- Left Info -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <span class="nav-link text-white-50 small d-none d-lg-inline">
                        Koperasi Karyawan Konsumsi
                    </span>
                </li>
            </ul>

            <!-- Right Actions: Role Context Switcher & User Profile -->
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2">
                
                {{-- Pemilih Konteks Peran (Mockup Tahap Awal) --}}
                {{-- // TODO: ganti dengan auth API --}}
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-2 px-3 py-1.5" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi {{ $activeInfo['icon'] }}"></i>
                        <span class="d-none d-sm-inline">Konteks:</span>
                        <span class="badge {{ $activeInfo['badge'] }} text-capitalize fw-semibold">
                            {{ $activeInfo['label'] }}
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                        <li><h6 class="dropdown-header text-uppercase fs-7 fw-bold">Ganti Konteks Peran</h6></li>
                        @foreach($availableRoles as $r)
                            @php $info = $roleBadges[$r] ?? null; @endphp
                            @if($info)
                                <li>
                                    <form method="POST" action="{{ route('mock.switch-role') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="role" value="{{ $r }}">
                                        <button type="submit" class="dropdown-item d-flex align-items-center justify-content-between py-2 {{ $currentRole === $r ? 'active' : '' }}">
                                            <span class="d-flex align-items-center gap-2">
                                                <i class="bi {{ $info['icon'] }}"></i>
                                                {{ $info['label'] }}
                                            </span>
                                            @if($currentRole === $r)
                                                <i class="bi bi-check-lg text-primary ms-3"></i>
                                            @endif
                                        </button>
                                    </form>
                                </li>
                            @endif
                        @endforeach
                        <li><hr class="dropdown-divider"></li>
                        <li class="px-3 py-1 text-muted small" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle me-1"></i> Satu akun dapat memegang beberapa peran.
                        </li>
                    </ul>
                </div>

                <!-- User Profile / Mock Session -->
                <div class="dropdown">
                    <button class="btn btn-dark btn-sm dropdown-toggle d-flex align-items-center gap-2 border-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.8rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <span class="text-white small fw-medium">User (Mock)</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                        <li><span class="dropdown-item-text text-muted small">ID Karyawan: EMP-001</span></li>
                        <li><a class="dropdown-item small" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Ke Halaman Login</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item small text-danger" href="{{ route('login') }}"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</nav>
