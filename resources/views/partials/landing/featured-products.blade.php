<section class="py-5 bg-light border-bottom">
    <div class="container px-3 px-lg-4">
        <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
            <div>
                <span class="text-primary text-uppercase fw-bold small" style="letter-spacing: 0.05em;">Stok Tersedia</span>
                <h3 class="fw-bold text-dark mb-0 fs-4">Produk Pilihan Gerai Koperasi</h3>
            </div>
            <div>
                <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5">
                    <span>Semua Katalog</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>

        <div class="row g-3 g-md-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-3">
            @foreach($featuredProducts as $item)
                <div class="col">
                    <div class="product-card">
                        <!-- Thumbnail Box with Clear Contrast -->
                        <div class="product-thumbnail-box">
                            <i class="bi {{ $item['icon'] }}"></i>
                            @if($item['badge'])
                                <span class="position-absolute top-0 start-0 m-2.5 badge bg-primary text-white small fw-semibold px-2 py-1">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </div>

                        <!-- Product Information -->
                        <div class="d-flex flex-column flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">
                                    {{ $item['category'] }}
                                </small>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-1" style="font-size: 0.68rem;">
                                    Stok: {{ $item['available_stock'] }} {{ $item['unit'] }}
                                </span>
                            </div>

                            <h5 class="product-title text-truncate" title="{{ $item['name'] }}">
                                {{ $item['name'] }}
                            </h5>

                            <!-- Price and Order Action -->
                            <div class="mt-auto pt-3 border-top d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="product-price">
                                        Rp {{ number_format($item['price'], 0, ',', '.') }}
                                    </div>
                                    <small class="text-muted" style="font-size: 0.72rem;">per {{ $item['unit'] }}</small>
                                </div>
                                <a href="{{ route('login') }}" class="btn btn-sm btn-primary px-3 py-1.5 d-inline-flex align-items-center gap-1.5 shadow-sm fw-semibold" title="Pesan sekarang">
                                    <i class="bi bi-cart-plus"></i>
                                    <span>Pesan</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
