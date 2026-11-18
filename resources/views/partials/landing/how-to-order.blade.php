{{-- ==============================================================================
   how-to-order.blade.php - Compact Horizontal Flow Bar (Pickup Only)
   - 4 Langkah belanja ringkas & efisien dengan jarak ikon terkalibrasi
   - Informasi lokasi gerai & bebas ongkos kirim dengan padding lapang
   ============================================================================== --}}
<section id="alur-pesan" class="py-5 bg-white border-bottom">
    <div class="container px-3 px-lg-4">
        
        <div class="text-center max-w-2xl mx-auto mb-4">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fw-semibold small mb-2">
                Alur Transaksi Resmi
            </span>
            <h2 class="fw-bold text-dark mb-1 fs-4">Sistem Belanja Pickup Only di Gerai Koperasi</h2>
            <p class="text-secondary small mb-0">
                Pesan dari mana saja, stok disiapkan oleh staf koperasi, dan diambil langsung di kasir gerai tanpa biaya pengiriman.
            </p>
        </div>

        <!-- Horizontal Flow Bar -->
        <div class="how-to-order-bar mb-4">
            <div class="row g-3 g-lg-4 row-cols-1 row-cols-sm-2 row-cols-lg-4">
                @foreach($howToOrder as $item)
                    <div class="col">
                        <div class="step-chip h-100">
                            <span class="step-chip-number">{{ sprintf('%02d', $item['step']) }}</span>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi {{ $item['icon'] ?? 'bi-check-circle' }} text-primary me-2 fs-6"></i>
                                    <h6 class="fw-bold text-dark mb-0 fs-6">{{ $item['title'] }}</h6>
                                </div>
                                <p class="text-secondary small mb-0 lh-sm" style="font-size: 0.78rem;">
                                    {{ $item['desc'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Store Pickup Notice Bar (Padded & Well-Spaced) -->
        <div class="pickup-notice-card d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-3 p-md-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary text-white rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bi bi-geo-alt-fill fs-5"></i>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold text-dark fs-6">Lokasi Pengambilan & Jam Operasional Gerai KOPEG Mart</h6>
                    <p class="text-secondary small mb-0 lh-base">
                        {{ config('koperasi.store_location', 'Jl. Kanayakan No. A11 Dago (depan Kampus POLMAN Bandung)') }} &bull; 
                        <strong>Buka: {{ config('koperasi.store_hours', 'Senin - Sabtu 07.00 - 18.00 WIB') }}</strong> (Minggu & Libur Nasional Tutup).
                    </p>
                </div>
            </div>
            @if(!empty($landingShowCart))
                <div class="d-flex align-items-center gap-2 flex-shrink-0 ps-md-3">
                    <button type="button" class="btn btn-outline-primary px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#cartDrawer">
                        <i class="bi bi-cart3 me-1"></i>
                        <span>Buka Keranjang</span>
                    </button>
                </div>
            @endif
        </div>

    </div>
</section>
