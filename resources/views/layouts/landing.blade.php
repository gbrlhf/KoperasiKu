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
</head>
<body class="landing-page">

    {{-- Landing Navbar --}}
    @include('partials.landing.navbar')

    {{-- Main Landing Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Landing Footer --}}
    @include('partials.landing.footer')

    @stack('scripts')
</body>
</html>
