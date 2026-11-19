<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') - {{ config('koperasi.brand_name', 'KoperasiKu') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.svg') }}">

    <!-- Vite Assets (Bootstrap 5, Chart.js, Icons, Sass) -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
<link rel="stylesheet" href="{{ asset('css/usability.css') }}?v=20261118">
</head>
<body class="ui-polished bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                {{-- Flash Alert --}}
                @include('partials.flash-alert')

                {{-- Guest Card Content --}}
                @yield('content')

                {{-- Footer Info --}}
                <div class="text-center text-muted small mt-4">
                    &copy; {{ date('Y') }} {{ config('koperasi.brand_name', 'KoperasiKu') }} &bull; Sistem Koperasi Karyawan
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')
<script src="{{ asset('js/usability.js') }}?v=20261118" defer></script>
</body>
</html>
