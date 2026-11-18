@extends('layouts.landing')

@section('title', config('koperasi.brand_name', 'KoperasiKu') . ' — Etalase Belanja Koperasi Konsumen Pegawai')

@section('content')

    {{-- 1. Hero Asymmetric Split Banners (Inspirasi e-mox) --}}
    @include('partials.landing.hero')

    {{-- 2. Kategori Belanja Populer (Explore Popular Categories - Avatar Bulat) --}}
    @include('partials.landing.categories')

    {{-- 3. Etalase Produk Multi-Kategori & Promo Banners (Best Deals, Triple Promo, Grocery, Fullwidth Piutang, Household, Snacks) --}}
    @include('partials.landing.storefront-sections')

    {{-- 4. Alur Pemesanan Ringkas (Horizontal Step Flow - Pickup Only Gerai Kanayakan Dago) --}}
    @include('partials.landing.how-to-order')

    {{-- 5. FAQ Belanja & Ketentuan Piutang Anggota --}}
    @include('partials.landing.faq')

    {{-- DRAWER & MODAL PEMESANAN LANGSUNG KONSUMEN / ANGGOTA --}}
    @if(!empty($landingShowCart))
        @include('partials.landing.cart-drawer')
        @include('partials.landing.checkout-modal')
    @endif
    @include('partials.landing.product-detail-modal')
    @include('partials.landing.login-required-modal')
    @include('partials.landing.member-profile-drawer')
    @if(!empty($landingShowCart))
        @include('partials.landing.mobile-cart-bar')
    @endif

@endsection

@push('scripts')
<script>
    window.allProducts = @json($allProductsMap ?? []);
    window.isUserLoggedIn = @json($isLoggedIn ?? false);
    window.currentUser = @json($member ?? null);

    window.copyMemberPickupCode = function (text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-clipboard-check text-success"></i> <span>Tersalin!</span>';
            setTimeout(() => {
                btn.innerHTML = originalHtml;
            }, 2000);
        }).catch(() => {
            alert('Kode Pickup: ' + text);
        });
    };

    // Auto-open drawer if requested in query parameter ?open=profile atau ?open=cart
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('open') === 'profile') {
            const drawerEl = document.getElementById('memberProfileDrawer');
            if (drawerEl && window.bootstrap) {
                const bsDrawer = window.bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
                bsDrawer.show();
            }
        } else if (urlParams.get('open') === 'cart') {
            const drawerEl = document.getElementById('cartDrawer');
            if (drawerEl && window.bootstrap) {
                const bsDrawer = window.bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
                bsDrawer.show();
            }
        }
    });
</script>
@endpush
