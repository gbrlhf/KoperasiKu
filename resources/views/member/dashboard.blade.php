@extends('layouts.app')

@section('title', 'Portal Anggota — KoperasiKu')

@push('styles')
    @vite(['resources/css/landing.css'])
    <style>
        /* ==============================================================================
           Member Dashboard Custom Styles (Anti-Slop Clean Aesthetic)
           ============================================================================== */
        .member-header-gradient {
            background: linear-gradient(135deg, #091724 0%, #0f2942 60%, #1d4ed8 100%);
            border-radius: 14px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.12);
        }

        .member-virtual-card {
            background: linear-gradient(135deg, #0f2338 0%, #16365c 50%, #1e40af 100%);
            border-radius: 12px;
            padding: 1.25rem;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.25);
            position: relative;
            overflow: hidden;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .member-virtual-card::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -20%;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .member-card-chip {
            width: 32px;
            height: 24px;
            background: linear-gradient(135deg, #fcd34d 0%, #d97706 100%);
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(255, 255, 255, 0.4), 0 1px 3px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .member-card-chip::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.25);
        }

        /* Metric Cards */
        .member-metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            height: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .member-metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
            border-color: #cbd5e1;
        }

        /* Boarding Pass Ticket */
        .pickup-ticket-card {
            background: #ffffff;
            border: 1.5px dashed #3b82f6;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 14px -2px rgba(37, 99, 235, 0.08);
            position: relative;
        }

        .pickup-ticket-header {
            background: linear-gradient(90deg, #eff6ff 0%, #dbeafe 100%);
            border-bottom: 1px solid #bfdbfe;
            padding: 0.75rem 1.25rem;
        }

        .pickup-code-box {
            background: #0f172a;
            color: #38bdf8;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
            border: 1px solid #334155;
        }

        /* Pulse indicator */
        .live-pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #16a34a;
            box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7);
            animation: pulse 1.8s infinite cubic-bezier(0.66, 0, 0, 1);
            display: inline-block;
        }

        @keyframes pulse {
            to {
                box-shadow: 0 0 0 10px rgba(22, 163, 74, 0);
            }
        }

        /* Custom Nav Tabs */
        .member-portal-nav .nav-link {
            font-weight: 600;
            font-size: 0.88rem;
            color: #475569;
            padding: 0.65rem 1.15rem;
            border: none;
            border-bottom: 2.5px solid transparent;
            border-radius: 0;
            background: transparent;
            transition: all 0.15s ease;
        }

        .member-portal-nav .nav-link:hover {
            color: #1d4ed8;
        }

        .member-portal-nav .nav-link.active {
            color: #1d4ed8;
            border-bottom-color: #1d4ed8;
            background: transparent;
        }
    </style>
@endpush

@section('content')

<div class="mb-4">
    <!-- 1. HERO BANNER: INFORMASI AKUN ANGGOTA -->
    <div class="member-header-gradient p-3 p-md-4 mb-4">
        <div class="row align-items-center g-4">
            
            <!-- Left Info: Personal Greeting & Status -->
            <div class="col-12 col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="badge bg-white text-primary fw-bold px-2.5 py-1 rounded-pill" style="font-size: 0.72rem;">
                        <i class="bi bi-shield-check me-1"></i> PORTAL RESMI ANGGOTA
                    </span>
                    <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50 px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1.5" style="font-size: 0.72rem;">
                        <span class="live-pulse-dot"></span> {{ $member['status'] ?? 'TERVERIFIKASI' }}
                    </span>
                </div>

                <h3 class="fw-extrabold text-white mb-1.5 fs-4 fs-md-3">
                    Selamat Datang, {{ $member['name'] }} 👋
                </h3>
                
                <p class="text-white text-opacity-80 small mb-3 pe-lg-4 lh-base" style="font-size: 0.85rem;">
                    {{ $member['unit'] }} &bull; No. Anggota: <strong>{{ $member['id'] }}</strong><br class="d-none d-sm-inline">
                    Pesan sembako keluarga bebas antre di Gerai Kanayakan A11 dan nikmati fasilitas piutang tempo 7 hari bunga 0%.
                </p>

                <!-- Action Shortcut Buttons -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-warning text-dark fw-bold btn-sm px-3 py-2 d-inline-flex align-items-center gap-1.5 shadow-sm" onclick="switchMemberTab('katalog-tab')">
                        <i class="bi bi-bag-plus-fill"></i>
                        <span>Belanja Sembako</span>
                    </button>
                    <button type="button" class="btn btn-outline-light btn-sm px-3 py-2 d-inline-flex align-items-center gap-1.5 position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer">
                        <i class="bi bi-cart3"></i>
                        <span>Buka Keranjang</span>
                        <span class="badge bg-danger rounded-pill d-none ms-1" id="desktopCartBadge">0</span>
                    </button>
                    <button type="button" class="btn btn-outline-light btn-sm px-3 py-2 d-inline-flex align-items-center gap-1.5" onclick="switchMemberTab('kredit-tab')">
                        <i class="bi bi-receipt"></i>
                        <span>Cek Tagihan (Rp {{ number_format($receivableStatus['total_debt'], 0, ',', '.') }})</span>
                    </button>
                </div>
            </div>

            <!-- Right Visual: Clean Member Status Card (Replaces Skeumorphic Card Clutter) -->
            <div class="col-12 col-lg-5">
                <div class="bg-white bg-opacity-10 rounded-3 p-3.5 border border-white border-opacity-20 text-white shadow-sm" style="backdrop-filter: blur(8px);">
                    <div class="d-flex justify-content-between align-items-center mb-2.5 pb-2 border-bottom border-white border-opacity-15">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-badge fs-5 text-warning"></i>
                            <span class="fw-bold small">KOPEG POLMAN BANDUNG</span>
                        </div>
                        <span class="badge bg-white text-dark fw-bold small">{{ $member['membership_tier'] ?? 'Anggota Aktif' }}</span>
                    </div>

                    <div class="d-flex flex-column gap-1.5 small text-white text-opacity-90" style="font-size: 0.8rem;">
                        <div class="d-flex justify-content-between">
                            <span class="text-white text-opacity-65">Nomor Anggota:</span>
                            <span class="font-monospace fw-bold">{{ $member['id'] }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-white text-opacity-65">Fasilitas Kredit:</span>
                            <span class="fw-bold text-warning">Tempo 7 Hari &bull; Bunga 0%</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-white text-opacity-65">Lokasi Pengambilan:</span>
                            <span class="fw-bold text-white">Gerai Kanayakan A11</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-white text-opacity-65">Jam Operasional:</span>
                            <span class="fw-semibold">07.00 - 18.00 WIB</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- 2. 4 FINANCIAL & CREDIT METRICS (Ringkasan Keuangan Anggota Koperasi - Fokus MVP) -->
    <div class="row g-3 mb-4">
        
        <!-- Metric 1: Sisa Limit Piutang Belanja (Tempo 7 Hari) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="member-metric-card">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                            Sisa Limit Belanja
                        </small>
                        <div class="p-1.5 rounded-2 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-wallet2 fs-6"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-extrabold text-success mb-1">
                        Rp {{ number_format($receivableStatus['available_limit'], 0, ',', '.') }}
                    </div>
                    <!-- Meter Bar -->
                    <div class="progress mb-2" style="height: 6px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ 100 - ($receivableStatus['limit_used_percent'] ?? 15) }}%;" aria-valuenow="{{ 100 - ($receivableStatus['limit_used_percent'] ?? 15) }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted" style="font-size: 0.72rem;">
                        <span>Plafon: Rp {{ number_format($receivableStatus['credit_limit'] / 1000000, 1) }} Jt</span>
                        <span class="fw-semibold text-dark">{{ 100 - ($receivableStatus['limit_used_percent'] ?? 15) }}% Tersedia</span>
                    </div>
                </div>
                <div class="pt-2 mt-2 border-top">
                    <small class="text-success fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.74rem;">
                        <i class="bi bi-check-circle-fill"></i> Plafon aktif siap dipakai belanja
                    </small>
                </div>
            </div>
        </div>

        <!-- Metric 2: Tagihan Berjalan / Hutang Aktif -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="member-metric-card">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                            Tagihan Berjalan
                        </small>
                        <div class="p-1.5 rounded-2 bg-warning-subtle text-warning-emphasis d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-clock-history fs-6"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-extrabold text-danger mb-1">
                        Rp {{ number_format($receivableStatus['total_debt'], 0, ',', '.') }}
                    </div>
                    <div class="badge bg-warning text-dark fw-bold rounded-pill mb-2 px-2.5 py-1" style="font-size: 0.7rem;">
                        <i class="bi bi-hourglass-split me-1"></i> Tempo: {{ $receivableStatus['days_remaining'] ?? 3 }} Hari Lagi
                    </div>
                    <p class="text-muted mb-0 lh-sm" style="font-size: 0.73rem;">
                        Jatuh tempo: <strong>{{ $receivableStatus['due_date'] }}</strong> (2 faktur aktif).
                    </p>
                </div>
                <div class="pt-2 mt-2 border-top d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-link p-0 text-primary text-decoration-none fw-bold" style="font-size: 0.75rem;" onclick="switchMemberTab('kredit-tab')">
                        <span>Rincian Faktur</span>
                        <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <span class="text-muted" style="font-size: 0.7rem;">Bunga 0%</span>
                </div>
            </div>
        </div>

        <!-- Metric 3: Pesanan Menunggu Pengambilan (MVP Operasional Gerai) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="member-metric-card">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                            Pesanan Siap Ambil
                        </small>
                        <div class="p-1.5 rounded-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-box-seam fs-6"></i>
                        </div>
                    </div>
                    @if(!empty($activeOrders))
                        <div class="fs-4 fw-extrabold text-primary mb-1">
                            1 Pesanan
                        </div>
                        <div class="badge bg-success-subtle text-success border border-success-subtle rounded-pill mb-2 px-2.5 py-1" style="font-size: 0.7rem;">
                            <i class="bi bi-check2-circle me-1"></i> Kode: {{ $activeOrders[0]['pickup_code'] }}
                        </div>
                        <p class="text-muted mb-0 lh-sm" style="font-size: 0.73rem;">
                            Total: <strong>Rp {{ number_format($activeOrders[0]['total_amount'], 0, ',', '.') }}</strong> di Gerai Kanayakan.
                        </p>
                    @else
                        <div class="fs-4 fw-extrabold text-muted mb-1">
                            0 Pesanan
                        </div>
                        <p class="text-muted mb-0 lh-sm" style="font-size: 0.73rem;">
                            Tidak ada pesanan menunggu diambil.
                        </p>
                    @endif
                </div>
                <div class="pt-2 mt-2 border-top">
                    <small class="text-muted d-inline-flex align-items-center gap-1" style="font-size: 0.74rem;">
                        <i class="bi bi-geo-alt-fill text-danger"></i> Ambil di Gerai Kanayakan A11
                    </small>
                </div>
            </div>
        </div>

        <!-- Metric 4: Status Kelayakan Piutang & Credit Hold -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="member-metric-card">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-secondary fw-bold text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.03em;">
                            Status Kredit Anggota
                        </small>
                        <div class="p-1.5 rounded-2 bg-info-subtle text-info-emphasis d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="bi bi-shield-check fs-6"></i>
                        </div>
                    </div>
                    <div class="fs-4 fw-extrabold text-dark mb-1">
                        LANCAR
                    </div>
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge bg-success text-white rounded-pill px-2 py-0.5" style="font-size: 0.7rem;">
                            <i class="bi bi-check2 me-1"></i> Bebas Belanja Tempo
                        </span>
                    </div>
                    <p class="text-muted mb-0 lh-sm" style="font-size: 0.73rem;">
                        Credit Hold: <strong>Rp 0</strong> &bull; Tidak ada tunggakan jatuh tempo.
                    </p>
                </div>
                <div class="pt-2 mt-2 border-top">
                    <small class="text-muted d-inline-flex align-items-center gap-1" style="font-size: 0.74rem;">
                        <i class="bi bi-info-circle-fill text-primary"></i> Plafon seragam Rp 1.000.000
                    </small>
                </div>
            </div>
        </div>

    </div>

    <!-- 3. ACTIVE PICKUP PASS (Boarding Pass Style Order Card) -->
    @if(!empty($activeOrders))
    <div class="pickup-ticket-card mb-4">
        <!-- Header Strip -->
        <div class="pickup-ticket-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="live-pulse-dot"></span>
                <span class="fw-bold text-primary small">PESANAN AKTIF SIAP DIAMBIL DI GERAI KANAYAKAN A11</span>
                <span class="badge bg-primary text-white rounded-1 small">{{ $activeOrders[0]['order_no'] }}</span>
            </div>
            <span class="badge bg-white text-dark border small fw-semibold">
                <i class="bi bi-clock me-1 text-primary"></i> Jam Gerai: {{ $activeOrders[0]['store_hours'] }}
            </span>
        </div>

        <div class="p-3 p-md-4">
            <div class="row g-4 align-items-center">
                <!-- Left: Order Details & Pickup Steps -->
                <div class="col-12 col-md-7 border-md-end pe-md-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-success text-white px-2.5 py-1 fw-bold rounded-pill" style="font-size: 0.72rem;">
                            <i class="bi bi-check-circle-fill me-1"></i> STATUS: SIAP DIAMBIL
                        </span>
                        <small class="text-muted">• {{ $activeOrders[0]['date'] }}</small>
                    </div>

                    <!-- Visual Order Step Tracker -->
                    <div class="d-flex align-items-center gap-2 mb-3 py-2 px-3 bg-light rounded-2 border" style="font-size: 0.75rem;">
                        <div class="d-flex align-items-center gap-1 text-success fw-bold">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>1. Dipesan</span>
                        </div>
                        <i class="bi bi-chevron-right text-muted" style="font-size: 0.7rem;"></i>
                        <div class="d-flex align-items-center gap-1 text-success fw-bold">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>2. Dikemas</span>
                        </div>
                        <i class="bi bi-chevron-right text-muted" style="font-size: 0.7rem;"></i>
                        <div class="d-flex align-items-center gap-1 text-primary fw-extrabold">
                            <i class="bi bi-shop"></i>
                            <span>3. Siap di Kasir</span>
                        </div>
                    </div>

                    <!-- Items Summary -->
                    <div class="mb-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.7rem;">Daftar Barang Belanja:</small>
                        <div class="d-flex flex-column gap-1">
                            @foreach($activeOrders[0]['items'] as $oi)
                                <div class="d-flex justify-content-between align-items-center small py-1 border-bottom border-light">
                                    <span class="text-dark fw-medium">{{ $oi['name'] }} ({{ $oi['qty'] }} {{ $oi['unit'] }})</span>
                                    <span class="fw-bold text-secondary">Rp {{ number_format($oi['price'] * $oi['qty'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block" style="font-size: 0.7rem;">Total Pembayaran:</small>
                            <span class="fs-5 fw-extrabold text-primary">Rp {{ number_format($activeOrders[0]['total_amount'], 0, ',', '.') }}</span>
                        </div>
                        <span class="badge bg-light text-secondary border px-2.5 py-1.5 small">
                            <i class="bi bi-cash-stack me-1"></i> {{ $activeOrders[0]['payment_label'] }}
                        </span>
                    </div>
                </div>

                <!-- Right: Official Pickup Pass Code -->
                <div class="col-12 col-md-5 text-center ps-md-4">
                    <div class="p-3 bg-light rounded-3 border">
                        <small class="text-muted fw-bold text-uppercase d-block mb-2" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                            Tunjukkan Kode Pickup ke Kasir:
                        </small>
                        
                        <div class="pickup-code-box mb-3" id="memberPickupCodeDisplay">
                            {{ $activeOrders[0]['pickup_code'] }}
                        </div>

                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-outline-primary btn-sm fw-semibold d-inline-flex align-items-center gap-1 px-3" onclick="copyMemberPickupCode('{{ $activeOrders[0]['pickup_code'] }}', this)">
                                <i class="bi bi-clipboard"></i>
                                <span>Salin Kode</span>
                            </button>
                            <button type="button" class="btn btn-primary btn-sm fw-semibold d-inline-flex align-items-center gap-1 px-3" data-bs-toggle="modal" data-bs-target="#pickupBarcodeModal">
                                <i class="bi bi-qr-code"></i>
                                <span>Lihat Barcode</span>
                            </button>
                        </div>

                        <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i> Jl. Kanayakan No. A11 Dago, Bandung
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- 4. TAB NAVIGATION STRIP (Dashboard, Belanja Sembako, Riwayat Tagihan, Riwayat Belanja) -->
    <div class="card border-0 shadow-sm bg-white overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs member-portal-nav border-0 px-3" id="memberPortalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ ($activeTab ?? 'dashboard') === 'dashboard' ? 'active' : '' }}" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard-pane" type="button" role="tab" aria-selected="true">
                        <i class="bi bi-grid-1x2-fill me-1.5"></i> Ringkasan & Pintasan
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ ($activeTab ?? '') === 'katalog' ? 'active' : '' }}" id="katalog-tab" data-bs-toggle="tab" data-bs-target="#katalog-pane" type="button" role="tab" aria-selected="false">
                        <i class="bi bi-bag-heart-fill me-1.5"></i> Katalog Sembako Anggota
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ ($activeTab ?? '') === 'kredit' ? 'active' : '' }}" id="kredit-tab" data-bs-toggle="tab" data-bs-target="#kredit-pane" type="button" role="tab" aria-selected="false">
                        <i class="bi bi-receipt me-1.5"></i> Faktur Piutang (Tempo 7 Hari)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ ($activeTab ?? '') === 'pesanan' ? 'active' : '' }}" id="pesanan-tab" data-bs-toggle="tab" data-bs-target="#pesanan-pane" type="button" role="tab" aria-selected="false">
                        <i class="bi bi-clock-history me-1.5"></i> Riwayat Belanja & Struk
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body p-3 p-md-4">
            <div class="tab-content" id="memberPortalTabsContent">
                
                <!-- PANE 1: RINGKASAN & PINTASAN DASHBOARD -->
                <div class="tab-pane fade {{ ($activeTab ?? 'dashboard') === 'dashboard' ? 'show active' : '' }}" id="dashboard-pane" role="tabpanel" tabindex="0">
                    <div class="row g-4">
                        
                        <!-- Left: Rekomendasi Belanja Cepat -->
                        <div class="col-12 col-lg-8">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="fw-bold text-dark mb-0 fs-6">Sembako Paling Sering Dipesan Anggota</h5>
                                    <small class="text-muted" style="font-size: 0.78rem;">Harga khusus anggota koperasi, ambil langsung tanpa antre</small>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" onclick="switchMemberTab('katalog-tab')">
                                    <span>Lihat Semua Katalog</span>
                                    <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>

                            <!-- 3 Quick Cards -->
                            <div class="row row-cols-1 row-cols-sm-3 g-3">
                                @foreach(array_slice($products, 0, 3) as $qp)
                                    <div class="col">
                                        <div class="card h-100 border p-2.5 shadow-none hover-shadow transition-base">
                                            <div class="bg-light rounded-2 overflow-hidden position-relative mb-2" style="height: 125px; cursor: pointer;" onclick="openProductDetailModal('{{ $qp['id'] }}')" role="button" title="Klik untuk rincian {{ $qp['name'] }}">
                                                <img src="{{ $qp['image'] }}" alt="{{ $qp['name'] }}" class="w-100 h-100 object-fit-cover" loading="lazy">
                                                @if(!empty($qp['badge']))
                                                    <span class="position-absolute top-0 start-0 m-2 badge bg-primary text-white" style="font-size: 0.65rem;">
                                                        {{ $qp['badge'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <small class="text-muted text-uppercase fw-bold" style="font-size: 0.68rem;">{{ $qp['category'] }}</small>
                                            <h6 class="fw-bold text-dark text-truncate mb-1" title="{{ $qp['name'] }}" onclick="openProductDetailModal('{{ $qp['id'] }}')" role="button" style="cursor: pointer;">
                                                {{ $qp['name'] }}
                                            </h6>
                                            <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
                                                <div>
                                                    <span class="fw-extrabold text-primary small">Rp {{ number_format($qp['price'], 0, ',', '.') }}</span>
                                                    <small class="text-muted d-block" style="font-size: 0.68rem;">/ {{ $qp['unit'] }}</small>
                                                </div>
                                                <button type="button" class="btn btn-primary btn-sm px-2 py-1 shadow-xs" onclick="openProductDetailModal('{{ $qp['id'] }}')" aria-label="Lihat {{ $qp['name'] }}">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Right: Panduan & Informasi Layanan Anggota -->
                        <div class="col-12 col-lg-4">
                            <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <h6 class="fw-bold text-dark mb-2">
                                        <i class="bi bi-info-circle-fill text-primary me-1"></i> Ketentuan Layanan Belanja
                                    </h6>
                                    <ul class="list-unstyled small text-secondary mb-3 d-flex flex-column gap-2" style="font-size: 0.8rem;">
                                        <li class="d-flex align-items-start gap-2">
                                            <i class="bi bi-check2-circle text-success mt-0.5"></i>
                                            <span><strong>Pickup Only:</strong> Bebas biaya kirim, pesanan disiapkan oleh staf kasir gerai.</span>
                                        </li>
                                        <li class="d-flex align-items-start gap-2">
                                            <i class="bi bi-check2-circle text-success mt-0.5"></i>
                                            <span><strong>Tempo 7 Hari:</strong> Bunga 0% terhitung sejak tanggal barang diserahkan fisik.</span>
                                        </li>
                                        <li class="d-flex align-items-start gap-2">
                                            <i class="bi bi-check2-circle text-success mt-0.5"></i>
                                            <span><strong>Lokasi Kasir:</strong> Depan Kampus POLMAN Bandung, buka Senin – Sabtu 07.00 – 18.00 WIB.</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="pt-2 border-top">
                                    <a href="{{ url('/') }}" class="btn btn-outline-secondary btn-sm w-100 fw-semibold d-inline-flex align-items-center justify-content-center gap-1">
                                        <i class="bi bi-shop"></i>
                                        <span>Buka Etalase Storefront Publik</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- PANE 2: KATALOG BELANJA LENGKAP ANGGOTA -->
                <div class="tab-pane fade {{ ($activeTab ?? '') === 'katalog' ? 'show active' : '' }}" id="katalog-pane" role="tabpanel" tabindex="0">
                    
                    <!-- Search & Filter Controls -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            @foreach($categories as $index => $cat)
                                <button type="button" class="btn btn-sm {{ $index === 0 ? 'btn-primary' : 'btn-outline-secondary' }} px-3 py-1 rounded-pill category-filter-btn" onclick="filterMemberCategory('{{ $cat }}', this)">
                                    {{ $cat }}
                                </button>
                            @endforeach
                        </div>
                        <div style="min-width: 250px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" placeholder="Cari beras, minyak, susu..." oninput="filterMemberCatalog(this.value)">
                            </div>
                        </div>
                    </div>

                    <!-- Products Grid (4-Column) -->
                    <div class="row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3" id="memberProductsList">
                        @foreach($products as $item)
                            <div class="col member-product-item" data-name="{{ strtolower($item['name']) }}" data-category="{{ strtolower($item['category']) }}">
                                <div class="card h-100 border p-2.5 shadow-none hover-shadow transition-base">
                                    
                                    <!-- Image Thumbnail -->
                                    <div class="bg-light rounded-2 d-flex align-items-center justify-content-center mb-2.5 position-relative overflow-hidden" style="height: 145px; cursor: pointer;" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" tabindex="0" title="Klik untuk lihat rincian {{ $item['name'] }}">
                                        @if(!empty($item['image']))
                                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="w-100 h-100 object-fit-cover" loading="lazy" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                                            <div class="w-100 h-100 d-none d-flex align-items-center justify-content-center text-secondary">
                                                <i class="bi {{ $item['icon'] }}" style="font-size: 2.5rem;"></i>
                                            </div>
                                        @else
                                            <i class="bi {{ $item['icon'] }} text-secondary" style="font-size: 2.5rem;"></i>
                                        @endif

                                        @if(!empty($item['badge']))
                                            <span class="position-absolute top-0 start-0 m-2 badge bg-primary text-white fw-bold" style="font-size: 0.65rem;">
                                                {{ $item['badge'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="d-flex flex-column flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted text-uppercase fw-bold text-truncate" style="font-size: 0.68rem;">
                                                {{ $item['category'] }}
                                            </small>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">
                                                Stok {{ $item['available_stock'] }}
                                            </span>
                                        </div>

                                        <div class="d-flex align-items-center gap-1 mb-1 small text-warning" style="font-size: 0.72rem;">
                                            <i class="bi bi-star-fill"></i>
                                            <span class="fw-bold text-dark">{{ $item['rating'] ?? 4.9 }}</span>
                                            <span class="text-muted">({{ $item['reviews_count'] ?? 20 }})</span>
                                        </div>

                                        <h6 class="fw-bold text-dark text-truncate mb-2" title="{{ $item['name'] }}" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" style="cursor: pointer;">
                                            {{ $item['name'] }}
                                        </h6>

                                        <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="fw-extrabold text-primary fs-6">
                                                    Rp {{ number_format($item['price'], 0, ',', '.') }}
                                                </div>
                                                @if(!empty($item['original_price']) && $item['original_price'] > $item['price'])
                                                    <del class="text-muted" style="font-size: 0.68rem;">Rp {{ number_format($item['original_price'], 0, ',', '.') }}</del>
                                                @else
                                                    <small class="text-muted" style="font-size: 0.68rem;">per {{ $item['unit'] }}</small>
                                                @endif
                                            </div>
                                            <button type="button" class="btn btn-sm btn-primary px-2.5 py-1 d-inline-flex align-items-center gap-1 shadow-xs fw-semibold" onclick="openProductDetailModal('{{ $item['id'] }}')">
                                                <i class="bi bi-eye"></i>
                                                <span>Detail</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>

                <!-- PANE 3: RINCIAN FAKTUR PIUTANG ANGGOTA (TEMPO 7 HARI) -->
                <div class="tab-pane fade {{ ($activeTab ?? '') === 'kredit' ? 'show active' : '' }}" id="kredit-pane" role="tabpanel" tabindex="0">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-0 fs-6">Daftar Faktur Piutang Belanja Anggota</h5>
                            <small class="text-muted" style="font-size: 0.78rem;">Fasilitas pembelian tempo 7 hari kalender bunga 0% khusus pegawai POLMAN Bandung</small>
                        </div>
                        <span class="badge bg-warning text-dark px-3 py-1.5 fw-bold rounded-pill">
                            <i class="bi bi-clock-history me-1"></i> Total Tagihan: Rp {{ number_format($receivableStatus['total_debt'], 0, ',', '.') }}
                        </span>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.84rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor Faktur</th>
                                    <th>Tanggal Ambil</th>
                                    <th>Uraian Komoditas</th>
                                    <th>Nominal</th>
                                    <th>Batas Jatuh Tempo</th>
                                    <th>Sisa Waktu</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receivableStatus['active_invoices'] as $inv)
                                    <tr>
                                        <td class="fw-bold font-monospace text-primary">{{ $inv['invoice_no'] }}</td>
                                        <td>{{ $inv['date'] }}</td>
                                        <td>{{ $inv['items_summary'] }}</td>
                                        <td class="fw-bold text-danger">Rp {{ number_format($inv['amount'], 0, ',', '.') }}</td>
                                        <td class="fw-semibold">{{ $inv['due_date'] }}</td>
                                        <td>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                                                {{ $inv['days_left'] }} Hari Lagi
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-0.5">
                                                BELUM LUNAS
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1" onclick="alert('Pelunasan faktur {{ $inv['invoice_no'] }} dapat dilakukan secara tunai atau QRIS di kasir gerai KOPEG Mart.')">
                                                <i class="bi bi-info-circle me-1"></i> Cara Bayar
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Payment Guide Box -->
                    <div class="p-3 rounded-3 bg-light border d-flex align-items-center gap-3 flex-wrap">
                        <div class="p-2.5 rounded-circle bg-white text-primary border shadow-xs d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                            <i class="bi bi-wallet2 fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold text-dark mb-0.5">Panduan Pelunasan Tagihan Anggota</h6>
                            <p class="text-muted small mb-0 lh-sm">
                                Pembayaran tagihan dapat dilakukan melalui kasir gerai KOPEG Mart Kanayakan A11 (Tunai / QRIS) atau transfer via rekening virtual koperasi.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- PANE 4: RIWAYAT PESANAN & STRUK BELANJA -->
                <div class="tab-pane fade {{ ($activeTab ?? '') === 'pesanan' ? 'show active' : '' }}" id="pesanan-pane" role="tabpanel" tabindex="0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0 fs-6">Riwayat Pengambilan Barang Anggota</h5>
                            <small class="text-muted" style="font-size: 0.78rem;">Daftar transaksi pesanan yang telah diserahkan di gerai</small>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        @foreach($orderHistory as $hist)
                            <div class="p-3 rounded-3 bg-light border d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-success text-white fw-bold px-2.5 py-0.5 rounded-pill" style="font-size: 0.7rem;">
                                            <i class="bi bi-check2 me-1"></i> SELESAI DIAMBIL
                                        </span>
                                        <span class="fw-bold font-monospace text-dark small">{{ $hist['order_no'] }}</span>
                                        <small class="text-muted">&bull; {{ $hist['date'] }}</small>
                                    </div>
                                    <p class="small text-secondary mb-1">
                                        {{ $hist['items_summary'] }} ({{ $hist['items_count'] }} Barang)
                                    </p>
                                    <small class="text-muted" style="font-size: 0.73rem;">
                                        Kode Pickup: <strong class="font-monospace text-dark">{{ $hist['pickup_code'] }}</strong> &bull; Diserahkan pada: {{ $hist['picked_up_at'] }}
                                    </small>
                                </div>
                                <div class="text-md-end">
                                    <div class="fs-5 fw-extrabold text-primary mb-1">
                                        Rp {{ number_format($hist['total_amount'], 0, ',', '.') }}
                                    </div>
                                    <span class="badge bg-white text-secondary border small">
                                        {{ $hist['payment_label'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- Modal Barcode / QR Pickup Code -->
<div class="modal fade" id="pickupBarcodeModal" tabindex="-1" aria-labelledby="pickupBarcodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark fs-6" id="pickupBarcodeModalLabel">
                    <i class="bi bi-qr-code text-primary me-2"></i> Kode Pengambilan Kasir Gerai
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-2 fw-semibold">
                    GERAI KOPEG MART KANAYAKAN A11
                </span>
                
                <h4 class="fw-extrabold text-dark mb-1">
                    {{ $activeOrders[0]['pickup_code'] ?? 'PKP-84920' }}
                </h4>
                <p class="text-muted small mb-3">Tunjukkan layar ini langsung ke petugas kasir saat pengambilan pesanan.</p>

                <!-- Real Scannable Barcode 1D (Garis) Standar Scanner Kasir -->
                <div class="p-3 bg-white border rounded-3 d-inline-block shadow-sm mb-3 text-center w-100" style="max-width: 340px;">
                    <div class="p-2.5 bg-white rounded border">
                        <svg id="memberPickupBarcodeSvg" class="w-100" style="max-height: 85px;"></svg>
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size: 0.72rem;">Format: Barcode 1D Code-128 (Standar Scanner Kasir)</small>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const modalEl = document.getElementById('pickupBarcodeModal');
                    if (modalEl) {
                        modalEl.addEventListener('shown.bs.modal', () => {
                            const code = "{{ $activeOrders[0]['pickup_code'] ?? 'PKP-84920' }}";
                            if (window.JsBarcode) {
                                try {
                                    window.JsBarcode("#memberPickupBarcodeSvg", code, {
                                        format: "CODE128",
                                        lineColor: "#000000",
                                        width: 2.2,
                                        height: 60,
                                        displayValue: true,
                                        fontSize: 14,
                                        margin: 6,
                                        background: "#ffffff"
                                    });
                                } catch(e) { console.warn("JsBarcode member error", e); }
                            }
                        });
                    }
                });
                </script>

                <div class="p-2.5 rounded-2 bg-light border text-start small text-secondary">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Penerima:</span>
                        <strong class="text-dark">{{ $member['name'] }} ({{ $member['id'] }})</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Total Belanja:</span>
                        <strong class="text-primary">Rp {{ number_format($activeOrders[0]['total_amount'] ?? 110500, 0, ',', '.') }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Status:</span>
                        <span class="badge bg-success text-white">Siap Diambil Sekarang</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- DRAWER & MODAL PEMESANAN LANGSUNG KONSUMEN / ANGGOTA --}}
@include('partials.landing.cart-drawer')
@include('partials.landing.checkout-modal')
@include('partials.landing.product-detail-modal')

@endsection

@push('scripts')
@vite(['resources/js/landing.js'])
<script>
    window.allProducts = @json($allProductsMap ?? []);

    function switchMemberTab(tabButtonId) {
        const btn = document.getElementById(tabButtonId);
        if (btn && window.bootstrap) {
            const tabTrigger = new window.bootstrap.Tab(btn);
            tabTrigger.show();
            btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function copyMemberPickupCode(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-clipboard-check text-success"></i> <span>Tersalin!</span>';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }).catch(() => {
            alert('Kode Pickup: ' + text);
        });
    }

    function filterMemberCatalog(rawQuery) {
        const query = (rawQuery || '').toLowerCase().trim();
        const items = document.querySelectorAll('.member-product-item');
        items.forEach(el => {
            const name = el.getAttribute('data-name') || '';
            const cat = el.getAttribute('data-category') || '';
            if (!query || name.includes(query) || cat.includes(query)) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });
    }

    function filterMemberCategory(categoryName, btn) {
        document.querySelectorAll('.category-filter-btn').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary');

        if (categoryName === 'Semua') {
            filterMemberCatalog('');
        } else {
            filterMemberCatalog(categoryName);
        }
    }
</script>
@endpush
