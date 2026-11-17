<section id="statistik" class="stats-section">
    <div class="container px-3 px-lg-4">
        <div class="text-center max-w-2xl mx-auto mb-5">
            <span class="badge bg-white bg-opacity-10 text-white rounded-pill px-3 py-1.5 fw-semibold small mb-2 border border-white border-opacity-10">
                Pencapaian & Kinerja
            </span>
            <h2 class="fw-bold text-white mb-2 fs-3">Kiprah Nyata untuk Kesejahteraan Anggota</h2>
            <p class="text-light text-opacity-75 small mb-0">
                Integrasi operasional gerai KOPEG Mart dengan platform digital KoperasiKu.
            </p>
        </div>

        {{-- // TODO: ambil dari API (/api/v1/analytics/summary) --}}
        <div class="row g-4 row-cols-2 row-cols-md-4">
            @foreach($statistics as $stat)
                <div class="col">
                    <div class="stat-item-box">
                        <div class="d-inline-flex align-items-center justify-content-center p-2 rounded-2 bg-white bg-opacity-10 text-warning mb-2" style="width: 40px; height: 40px;">
                            <i class="bi {{ $stat['icon'] }} fs-5"></i>
                        </div>
                        <div class="stat-number-display">
                            <span class="counter-val" data-target="{{ $stat['value'] }}">0</span><span>{{ $stat['suffix'] }}</span>
                        </div>
                        <div class="fw-bold text-white fs-6 mb-0.5">{{ $stat['label'] }}</div>
                        <small class="text-light text-opacity-75 d-block" style="font-size: 0.75rem;">{{ $stat['desc'] }}</small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
