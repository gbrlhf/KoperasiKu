@php
    $currentRole = $uiRole ?? session('active_role', 'MEMBER');
@endphp

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top border-bottom border-secondary-subtle py-2">
    <div class="container-fluid w-100 px-3 px-lg-4">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white fs-5" href="{{ url('/') }}">
            <div class="bg-primary text-white rounded-3 p-1.5 d-flex align-items-center justify-content-center shadow-xs" style="width: 34px; height: 34px;">
                <i class="bi bi-intersect fs-6" aria-hidden="true"></i>
            </div>
            <span>{{ config('koperasi.brand_name', 'KoperasiKu') }}</span>
        </a>

        <!-- Mobile Toggler -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <!-- Right Actions: User Profile & Storefront Link -->
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 ms-auto">
                
                <!-- Buka Etalase Publik Link -->
                <a href="{{ url('/') }}" class="btn btn-outline-light btn-sm px-2.5 py-1.5 d-inline-flex align-items-center gap-1.5 text-white-50 hover-text-white" style="font-size: 0.8rem;">
                    <i class="bi bi-shop-window"></i>
                    <span>Etalase Publik</span>
                </a>

                @php
                    $authUser = auth()->user();
                    $sessionUser = session('user');
                    $displayName = $authUser?->member?->full_name
                        ?? $authUser?->name
                        ?? ($sessionUser['name'] ?? match($currentRole) {
                            'CASHIER' => 'Petugas Kasir Gerai',
                            'MANAGER' => 'Manajer Operasional',
                            default => 'Ahmad Fauzi',
                        });
                    $rawId = (string) ($authUser?->id ?? ($sessionUser['id'] ?? match($currentRole) {
                        'CASHIER' => 'KASIR-01',
                        'MANAGER' => 'MGR-01',
                        default => 'EMP-001',
                    }));
                    $userEmail = $authUser?->email ?? ($sessionUser['email'] ?? null);
                    $employeeCode = $authUser?->member?->employee_number
                        ?? $authUser?->employee_id
                        ?? ($sessionUser['employee_id'] ?? ($sessionUser['member_no'] ?? null));
                    if ($employeeCode) {
                        $displayProfileMeta = 'ID: ' . $employeeCode;
                    } elseif ($userEmail) {
                        $displayProfileMeta = $userEmail;
                    } elseif (strlen($rawId) > 16) {
                        $displayProfileMeta = 'ID: #' . strtoupper(substr($rawId, -6));
                    } else {
                        $displayProfileMeta = 'ID: ' . $rawId;
                    }
                @endphp
                <!-- User Profile / Mock Session -->
                <div class="dropdown">
                    <button class="btn btn-dark btn-sm dropdown-toggle d-flex align-items-center gap-2 border-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.8rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <span class="text-white small fw-medium">{{ $displayName }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="min-width: 200px; max-width: 260px;">
                        <li><span class="dropdown-item-text text-muted small text-truncate d-block" title="{{ $displayProfileMeta }}">{{ $displayProfileMeta }}</span></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="dropdown-item small text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button></form></li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</nav>
