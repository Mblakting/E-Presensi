<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Fitur Lomba') · {{ config('app.name', 'E-Presensi') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}" sizes="32x32">
    <link href="{{ asset('tabler/dist/css/tabler.min.css') }}" rel="stylesheet">
    <link href="{{ asset('tabler/dist/css/tabler-vendors.min.css') }}" rel="stylesheet">
    <style>
        html, body { min-height: 100%; }
        body { overflow-x: hidden; }
        .competition-main { max-width: 1280px; }
        .competition-break { overflow-wrap: anywhere; }
        .competition-status { white-space: nowrap; }
        @media (max-width: 575.98px) {
            .competition-main { padding-left: 1rem; padding-right: 1rem; }
            .competition-page-title { font-size: 1.35rem; }
        }
    </style>
</head>
<body>
    <header class="navbar navbar-expand-md navbar-light d-print-none">
        <div class="container-xl">
            <a class="navbar-brand" href="{{ url('/competition') }}">Pusat Fitur Lomba</a>
            <nav class="navbar-nav flex-row ms-auto gap-3" aria-label="Navigasi kompetisi">
                <a class="nav-link" href="{{ url('/') }}">Beranda aplikasi</a>
                <a class="nav-link" href="{{ url('/competition') }}">Pusat lomba</a>
                <a class="nav-link" href="{{ url('/competition/guide') }}">Panduan juri</a>
                @if (auth('user')->check())
                    @if (Route::has('competition.evaluation.index'))
                        <a class="nav-link d-none d-md-inline" href="{{ route('competition.evaluation.index') }}">Evaluasi</a>
                    @endif
                    @if (Route::has('competition.reviews.index'))
                        <a class="nav-link d-none d-md-inline" href="{{ route('competition.reviews.index') }}">Review</a>
                    @endif
                    @if (Route::has('competition.audit.index'))
                        <a class="nav-link d-none d-md-inline" href="{{ route('competition.audit.index') }}">Audit</a>
                    @endif
                    @if (Route::has('competition.consents.index'))
                        <a class="nav-link d-none d-md-inline" href="{{ route('competition.consents.index') }}">Consent</a>
                    @endif
                @elseif (auth('siswa')->check())
                    <a class="nav-link" href="{{ route('presensi.face') }}">Presensi wajah</a>
                    @if (Route::has('competition.student-consents.index'))
                        <a class="nav-link" href="{{ route('competition.student-consents.index') }}">Persetujuan</a>
                    @endif
                @else
                    <a class="nav-link" href="{{ route('loginadmin') }}">Login admin</a>
                @endif
            </nav>
        </div>
    </header>
    <main class="container-xl competition-main py-4">
        <nav aria-label="Jejak navigasi" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">Beranda aplikasi</a></li>
                <li class="breadcrumb-item"><a href="{{ url('/competition') }}">Pusat lomba</a></li>
                <li class="breadcrumb-item active" aria-current="page">@yield('breadcrumb', 'Informasi')</li>
            </ol>
        </nav>
        @yield('content')
    </main>
</body>
</html>
