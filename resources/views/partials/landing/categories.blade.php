{{-- ==============================================================================
   categories.blade.php - Explore Popular Categories (Inspirasi e-mox Circle Avatars)
   - Ikon lingkaran bergaya avatar e-commerce modern
   - Label kategori dan jumlah item tersedia
   - Mendukung klik filter langsung di etalase
   ============================================================================== --}}
<section class="py-4 bg-white border-bottom" id="kategori-section">
    <div class="container px-3 px-lg-4">
        
        <!-- Section Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="fw-bold text-dark mb-0 fs-5">Kategori Belanja Populer</h3>
            </div>
            <button type="button" class="btn btn-link text-primary text-decoration-none fw-semibold small p-0 d-inline-flex align-items-center gap-1" onclick="resetStorefrontFilter()">
                <span>Tampilkan Semua Produk ({{ count($categories) }} Kategori)</span>
                <i class="bi bi-chevron-right" style="font-size: 0.75rem;"></i>
            </button>
        </div>

        <!-- Circular Category Grid (8 Columns on Large Screens) -->
        <div class="row row-cols-4 row-cols-sm-4 row-cols-md-4 row-cols-lg-8 g-2 g-sm-3 justify-content-center">
            @foreach($categories as $cat)
                <div class="col">
                    <button type="button" class="category-circle-item w-100 border-0 bg-transparent" onclick="filterStorefrontCategory('{{ addslashes($cat['name']) }}')" title="Filter kategori {{ $cat['name'] }}">
                        <div class="category-circle-avatar">
                            <i class="bi {{ $cat['icon'] }}" aria-hidden="true"></i>
                        </div>
                        <span class="category-circle-name text-truncate w-100" title="{{ $cat['name'] }}">
                            {{ $cat['name'] }}
                        </span>
                        <small class="text-muted mt-0.5" style="font-size: 0.68rem;">
                            {{ $cat['item_count'] ?? '' }}
                        </small>
                    </button>
                </div>
            @endforeach
        </div>

    </div>
</section>
