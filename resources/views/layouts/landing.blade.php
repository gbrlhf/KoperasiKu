<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('koperasi.brand_name') . ' — ' . config('koperasi.tagline'))</title>
    <meta name="description" content="{{ config('koperasi.slogan') }} - Ambil langsung di koperasi (Pickup Only).">

    <!-- Assets Vite (Bootstrap 5, Icons, Plus Jakarta Sans, Custom Landing CSS & JS) -->
    @vite([
        'resources/sass/app.scss',
        'resources/css/landing.css',
        'resources/js/app.js',
        'resources/js/landing.js',
    ])
    @stack('styles')
<link rel="stylesheet" href="{{ asset('css/usability.css') }}?v=20261118">
</head>
<body class="ui-polished landing-page">
@php
    $landingAuthUser = auth()->user();
    $landingSessionUser = session('user', []);
    $landingIsLoggedIn = !empty($isLoggedIn) && (!empty($landingAuthUser) || !empty($landingSessionUser));
    $landingRoles = array_values(array_unique(array_map('strtoupper', (array) session(
        'available_roles',
        $landingAuthUser ? $landingAuthUser->roleCodes() : []
    ))));
    $landingActiveRole = strtoupper((string) session('active_role', $landingRoles[0] ?? ($landingIsLoggedIn ? 'MEMBER' : '')));

    $landingRoleLabel = match($landingActiveRole) {
        'CASHIER' => 'Kasir',
        'MANAGER' => 'Manajer',
        default => 'Anggota',
    };

    $landingIsMemberRole = $landingIsLoggedIn && $landingActiveRole === 'MEMBER';
    $landingIsStaffRole = $landingIsLoggedIn && in_array($landingActiveRole, ['CASHIER', 'MANAGER'], true);
    $landingShowCart = !$landingIsStaffRole;
    $landingHasCashierRole = $landingIsLoggedIn && ($landingActiveRole === 'CASHIER' || in_array('CASHIER', $landingRoles, true));
    $landingHasManagerRole = $landingIsLoggedIn && ($landingActiveRole === 'MANAGER' || in_array('MANAGER', $landingRoles, true));

    $landingMemberRecord = $landingAuthUser?->member;
    $landingHasActiveMember = $landingMemberRecord
        ? strtoupper((string) ($landingMemberRecord->status ?? 'ACTIVE')) === 'ACTIVE'
        : (!empty($landingSessionUser['member_id']) || (empty($landingAuthUser) && !empty($landingSessionUser) && $landingActiveRole === 'MEMBER'));

    $landingShowCreditWidget = $landingIsLoggedIn
        && $landingActiveRole === 'MEMBER'
        && $landingHasActiveMember;

    $rawLandingCode = (string) ($member['id'] ?? ($landingSessionUser['id'] ?? ''));
    $landingMemberCode = ($rawLandingCode !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $rawLandingCode))
        ? $rawLandingCode
        : null;
@endphp

    {{-- Landing Navbar --}}
    @include('partials.landing.navbar')

    {{-- Main Landing Content --}}
    <a class="skip-content" href="#main-content">Lewati ke katalog</a><main id="main-content" tabindex="-1">
        @yield('content')
    </main>

    {{-- Landing Footer --}}
    @include('partials.landing.footer')

    @stack('scripts')
<script src="{{ asset('js/usability.js') }}?v=20261118" defer></script>
</body>
</html>
