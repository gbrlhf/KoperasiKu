<div class="product-card h-100 d-flex flex-column">
    <!-- 1. Thumbnail Box Rasio Persegi 1:1 (Tokopedia Style) -->
    <div class="product-thumbnail-box" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" tabindex="0" title="Klik untuk lihat detail {{ $item['name'] }}" style="cursor: pointer;">
        @if(!empty($item['image']))
            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" class="product-thumb-img" loading="lazy" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
            <div class="product-icon-fallback d-none w-100 h-100 d-flex align-items-center justify-content-center">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
            </div>
        @else
            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
        @endif

        @if(!empty($item['badge']))
            <span class="position-absolute top-0 start-0 m-2 badge bg-danger text-white fw-bold rounded-1 shadow-xs" style="font-size: 0.65rem; letter-spacing: 0.02em;">
                {{ $item['badge'] }}
            </span>
        @endif
    </div>

    <!-- 2. Hierarki Konten Tokopedia Marketplace -->
    <div class="product-card-body d-flex flex-column flex-grow-1">
        <!-- Judul Produk (Maksimal 2 Baris Ellipsis) -->
        <h5 class="product-title" title="{{ $item['name'] }}" onclick="openProductDetailModal('{{ $item['id'] }}')" role="button" style="cursor: pointer;">
            {{ $item['name'] }}
        </h5>

        <!-- Harga Utama Tebal -->
        <div class="product-price-box">
            <span class="product-price-currency">Rp</span>
            <span class="product-price-number">{{ number_format($item['price'], 0, ',', '.') }}</span>
        </div>

        <!-- Badge Persentase Diskon Merah & Harga Coret -->
        <div class="product-discount-row mb-1">
            @if(!empty($item['original_price']) && $item['original_price'] > $item['price'])
                @php
                    $discountPct = round((($item['original_price'] - $item['price']) / $item['original_price']) * 100);
                @endphp
                <span class="badge-discount">-{{ $discountPct }}%</span>
                <del class="product-price-slashed">Rp {{ number_format($item['original_price'], 0, ',', '.') }}</del>
            @else
                <small class="text-muted" style="font-size: 0.7rem;">Harga resmi / {{ $item['unit'] }}</small>
            @endif
        </div>

        <!-- Lencana Toko Resmi Tokopedia (Gerai Kanayakan A11) -->
        <div class="product-store-badge mb-1">
            <i class="bi bi-patch-check-fill text-success fs-7" aria-hidden="true"></i>
            <span class="text-truncate">Gerai Kanayakan A11</span>
        </div>

        <!-- Rating Bintang, Terjual, & Badge Sisa Stok -->
        <div class="product-meta-row mt-auto pt-2 border-top d-flex align-items-center justify-content-between gap-1">
            <div class="product-rating-bar">
                <i class="bi bi-star-fill text-warning me-1"></i>
                <span class="fw-bold text-dark">{{ $item['rating'] ?? 4.9 }}</span>
                @if(!empty($item['sold_count']))
                    <span class="text-muted ms-1 d-none d-sm-inline">• {{ $item['sold_count'] }}</span>
                @endif
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-1 font-monospace" style="font-size: 0.65rem;">
                Stok {{ $item['available_stock'] }}
            </span>
        </div>

        <!-- Tombol + Keranjang Tokopedia -->
        <div class="pt-2 mt-1">
            <button type="button" class="btn btn-outline-primary btn-sm w-100 fw-bold py-1.5 d-inline-flex align-items-center justify-content-center gap-1.5 btn-add-cart-tokopedia shadow-xs" onclick="addToCart('{{ $item['id'] }}', '{{ addslashes($item['name']) }}', {{ $item['price'] }}, '{{ $item['unit'] }}', '{{ $item['icon'] }}')" aria-label="Tambah {{ $item['name'] }} ke keranjang">
                <i class="bi bi-cart-plus"></i>
                <span>+ Keranjang</span>
            </button>
        </div>
    </div>
</div>
