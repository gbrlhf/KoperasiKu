@extends('layouts.landing')

@section('title', config('koperasi.brand_name', 'KoperasiKu') . ' - KOPEG POLMAN Bandung')

@php
    $landingAuthUser = auth()->user();
    $landingSessionUser = session('user', []);
    $landingIsLoggedIn = !empty($isLoggedIn) && (!empty($landingAuthUser) || !empty($landingSessionUser));
    $landingRoles = array_values(array_unique(array_map('strtoupper', (array) session(
        'available_roles',
        $landingAuthUser ? $landingAuthUser->roleCodes() : []
    ))));
    $landingActiveRole = strtoupper((string) session('active_role', $landingRoles[0] ?? ($landingIsLoggedIn ? 'MEMBER' : '')));
    if ($landingIsLoggedIn && !in_array($landingActiveRole, ['CASHIER', 'MANAGER'], true)) {
        $landingActiveRole = 'MEMBER';
    }
    $landingRoleLabel = $landingRoleLabel ?? match($landingActiveRole) {
        'CASHIER' => 'Kasir',
        'MANAGER' => 'Manajer',
        default => 'Anggota',
    };
    $landingIsMemberRole = $landingIsMemberRole ?? ($landingIsLoggedIn && $landingActiveRole === 'MEMBER');
    $landingIsStaffRole = $landingIsStaffRole ?? ($landingIsLoggedIn && in_array($landingActiveRole, ['CASHIER', 'MANAGER'], true));
    $landingShowCart = $landingShowCart ?? !$landingIsStaffRole;
    $landingHasCashierRole = $landingHasCashierRole ?? ($landingIsLoggedIn && ($landingActiveRole === 'CASHIER' || in_array('CASHIER', $landingRoles, true)));
    $landingHasManagerRole = $landingHasManagerRole ?? ($landingIsLoggedIn && ($landingActiveRole === 'MANAGER' || in_array('MANAGER', $landingRoles, true)));
    $landingMemberRecord = $landingAuthUser?->member;
    $landingHasActiveMember = $landingMemberRecord
        ? strtoupper((string) ($landingMemberRecord->status ?? 'ACTIVE')) === 'ACTIVE'
        : (!empty($landingSessionUser['member_id']) || (empty($landingAuthUser) && !empty($landingSessionUser) && $landingActiveRole === 'MEMBER'));
    $landingShowCreditWidget = $landingShowCreditWidget ?? ($landingIsLoggedIn && $landingIsMemberRole && $landingHasActiveMember);
    $rawLandingCode = (string) ($member['id'] ?? ($landingSessionUser['id'] ?? ''));
    $landingMemberCode = $landingMemberCode ?? (($rawLandingCode !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $rawLandingCode))
        ? $rawLandingCode
        : null);
@endphp

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
    window.allProducts = @js($allProductsMap ?? []);
    window.isUserLoggedIn = @js($isLoggedIn ?? false);
    window.currentUser = @js($member ?? null);
    @if(auth()->check() && session('active_role') === 'MEMBER' && session('user.email'))
    // Only an authenticated member session may populate the remembered identity.
    try {
        const identity = {{ Illuminate\Support\Js::from(['id' => session('user.email'), 'name' => session('user.name')]) }};
        localStorage.setItem('koperasiku_verified_member_v2', JSON.stringify(identity));
    } catch (e) {}
    @endif

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
