<section id="galeri" class="py-5 bg-light border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="text-center max-w-2xl mx-auto mb-5">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1.5 fw-semibold small mb-2">
                Dokumentasi Gerai
            </span>
            <h2 class="fw-bold text-dark mb-2 fs-3">Aktivitas & Fasilitas Koperasi</h2>
            <p class="text-secondary small mb-0">
                Suasana pelayanan, gudang persediaan, dan operasional gerai KOPEG Mart Kanayakan.
            </p>
        </div>

        <div class="row g-3 g-md-4 row-cols-1 row-cols-sm-2 row-cols-md-3">
            @foreach($gallery as $gal)
                <div class="col">
                    <div class="gallery-card-box">
                        <div class="gallery-thumbnail-placeholder">
                            <i class="bi {{ $gal['icon'] }} fs-2 mb-2 text-primary"></i>
                            <span class="badge bg-white text-secondary border small mb-1.5">{{ $gal['category'] }}</span>
                            <div class="fw-semibold text-dark text-center small px-2">{{ $gal['title'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
