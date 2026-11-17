<section class="py-5 bg-white border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="text-primary text-uppercase fw-bold small" style="letter-spacing: 0.05em;">Publikasi Koperasi</span>
                <h3 class="fw-bold text-dark mb-0 fs-4">Kabar & Informasi Terkini</h3>
            </div>
            <div>
                <a href="{{ config('koperasi.external_links.berita_lama') }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5">
                    <span>Arsip Berita KOPEG</span>
                    <i class="bi bi-box-arrow-up-right small"></i>
                </a>
            </div>
        </div>

        <div class="row g-4 row-cols-1 row-cols-md-3">
            @foreach($news as $item)
                <div class="col">
                    <div class="news-card">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small fw-semibold">
                                {{ $item['category'] }}
                            </span>
                            <small class="text-muted" style="font-size: 0.72rem;">
                                <i class="bi bi-calendar3 me-1"></i>{{ $item['date'] }}
                            </small>
                        </div>

                        <h5 class="fw-bold text-dark mb-2 fs-6 lh-base">
                            <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer" class="text-dark text-decoration-none">
                                {{ $item['title'] }}
                            </a>
                        </h5>

                        <p class="text-secondary small mb-4 flex-grow-1 lh-sm">
                            {{ $item['summary'] }}
                        </p>

                        <div class="pt-3 border-top mt-auto">
                            <a href="{{ $item['link'] }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none fw-semibold small d-inline-flex align-items-center gap-1">
                                <span>Baca Rilis Selengkapnya</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
