<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Masuk') — KOPERADATA</title>

    <!-- Vite Assets (Bootstrap 5, Chart.js, Icons, Sass) -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                {{-- Flash Alert --}}
                @include('partials.flash-alert')

                {{-- Guest Card Content --}}
                @yield('content')

                {{-- Footer Info --}}
                <div class="text-center text-muted small mt-4">
                    &copy; {{ date('Y') }} KOPERADATA &bull; Sistem Koperasi Karyawan
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
