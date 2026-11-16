@props([
    'status' => '',
])

@php
    $statusKey = strtoupper(trim($status));
    
    $badgeConfig = match($statusKey) {
        // Order Status
        'PENDING_PICKUP' => ['class' => 'bg-warning text-dark', 'label' => 'Menunggu Diambil', 'icon' => 'bi-hourglass-split'],
        'COMPLETED' => ['class' => 'bg-success', 'label' => 'Selesai', 'icon' => 'bi-check-circle-fill'],
        'CANCELLED' => ['class' => 'bg-danger', 'label' => 'Dibatalkan', 'icon' => 'bi-x-circle-fill'],

        // Payment Status
        'PENDING_VERIFICATION' => ['class' => 'bg-info text-dark', 'label' => 'Menunggu Verifikasi', 'icon' => 'bi-clock-history'],
        'VERIFIED' => ['class' => 'bg-success', 'label' => 'Terverifikasi', 'icon' => 'bi-patch-check-fill'],
        'REJECTED' => ['class' => 'bg-danger', 'label' => 'Ditolak', 'icon' => 'bi-exclamation-triangle-fill'],

        // Receivable Status
        'UNPAID' => ['class' => 'bg-secondary', 'label' => 'Belum Lunas', 'icon' => 'bi-dash-circle'],
        'PARTIALLY_PAID' => ['class' => 'bg-primary', 'label' => 'Sebagian Dibayar', 'icon' => 'bi-pie-chart-fill'],
        'PAID' => ['class' => 'bg-success', 'label' => 'Lunas', 'icon' => 'bi-check2-all'],
        'OVERDUE' => ['class' => 'bg-danger', 'label' => 'Lewat Jatuh Tempo', 'icon' => 'bi-exclamation-octagon-fill'],

        // Membership / Master
        'ACTIVE' => ['class' => 'bg-success', 'label' => 'Aktif', 'icon' => 'bi-check-lg'],
        'INACTIVE' => ['class' => 'bg-secondary', 'label' => 'Nonaktif', 'icon' => 'bi-pause-circle'],

        default => ['class' => 'bg-light text-dark border', 'label' => $status ?: 'Tidak Diketahui', 'icon' => 'bi-dot'],
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . $badgeConfig['class'] . ' d-inline-flex align-items-center gap-1 px-2.5 py-1.5 fw-medium rounded-pill']) }}>
    @if($badgeConfig['icon'])
        <i class="bi {{ $badgeConfig['icon'] }}"></i>
    @endif
    <span>{{ $badgeConfig['label'] }}</span>
</span>
