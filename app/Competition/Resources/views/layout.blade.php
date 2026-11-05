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
        html, body { overflow-x: clip; }
        .competition-break { overflow-wrap: anywhere; }
        .competition-status { white-space: normal; }
        .competition-header-inner, .competition-main { width: 100%; }
        .competition-main { overflow-x: clip; }
        .competition-nav, .competition-mobile-links { gap: .5rem; }
        .competition-nav .btn, .competition-mobile-links .btn,
        .competition-user-actions .btn, .competition-header details > summary {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
        }
        .competition-nav .btn[aria-current="page"],
        .competition-mobile-links .btn[aria-current="page"] {
            color: var(--tblr-primary);
            background: var(--tblr-primary-lt);
            border-color: var(--tblr-primary);
            font-weight: 700;
        }
        .competition-mobile-links { display: flex; flex-direction: column; padding: .5rem 0 0; }
        .competition-mobile-links .btn { justify-content: flex-start; }
        .competition-header details > summary { cursor: pointer; list-style: none; }
        .competition-header details > summary::-webkit-details-marker { display: none; }
        .competition-skip-link {
            position: absolute; left: .75rem; top: -5rem; z-index: 1100;
            padding: .75rem 1rem; background: #fff; color: #111; border: 2px solid #111;
        }
        .competition-skip-link:focus { top: .75rem; }
        :focus-visible { outline: 3px solid #1457a6 !important; outline-offset: 2px; }
        @media (max-width: 767.98px) {
            .competition-desktop-nav { display: none !important; }
            .competition-header .container-xl { align-items: flex-start; }
            .competition-main { padding-top: 1rem !important; }
            .competition-user-chip { width: 100%; }
        }
        @media (min-width: 768px) {
            .competition-mobile-nav { display: none !important; }
        }
        @media (max-width: 575.98px) {
            .competition-page-title { font-size: 1.35rem; }
        }
    </style>
</head>
<body>
    <a class="competition-skip-link" href="#competition-content">Lewati ke konten</a>
    @php
        $competitionHomeUrl = auth('user')->check()
            ? route('dashboardadmin')
            : (auth('siswa')->check() ? route('dashboard') : route('login'));
        $competitionHomeLabel = auth('user')->check()
            ? 'Dashboard admin'
            : (auth('siswa')->check() ? 'Dashboard siswa' : 'Beranda');
        $competitionUser = auth('user')->user() ?? auth('siswa')->user();
        $competitionRole = auth('user')->check() ? 'Admin' : (auth('siswa')->check() ? 'Siswa' : null);
    @endphp
    <header class="navbar navbar-light d-print-none competition-header">
        <div class="container-xl competition-header-inner flex-wrap gap-3">
            <a class="navbar-brand m-0" href="{{ url('/competition') }}">Pusat Fitur Lomba</a>
            <nav class="competition-nav competition-desktop-nav d-flex flex-wrap align-items-center" aria-label="Navigasi kompetisi">
                <a class="btn btn-outline-primary" href="{{ $competitionHomeUrl }}">{{ $competitionHomeLabel }}</a>
                <a class="btn btn-outline-primary" href="{{ url('/competition') }}" @if (request()->routeIs('competition.home')) aria-current="page" @endif>Pusat lomba</a>
                <a class="btn btn-outline-primary" href="{{ url('/competition/guide') }}" @if (request()->routeIs('competition.guide')) aria-current="page" @endif>Panduan juri</a>
                @if (auth('user')->check())
                    @if (Route::has('competition.evaluation.index'))
                        <a class="btn btn-outline-primary" href="{{ route('competition.evaluation.index') }}" @if (request()->routeIs('competition.evaluation.*')) aria-current="page" @endif>Evaluasi AI</a>
                    @endif
                    @if (Route::has('competition.reviews.index'))
                        <a class="btn btn-outline-primary" href="{{ route('competition.reviews.index') }}" @if (request()->routeIs('competition.reviews.*')) aria-current="page" @endif>Antrean review</a>
                    @endif
                    @if (Route::has('competition.audit.index'))
                        <a class="btn btn-outline-primary" href="{{ route('competition.audit.index') }}" @if (request()->routeIs('competition.audit.*')) aria-current="page" @endif>Log audit</a>
                    @endif
                    @if (Route::has('competition.consents.index'))
                        <a class="btn btn-outline-primary" href="{{ route('competition.consents.index') }}" @if (request()->routeIs('competition.consents.*')) aria-current="page" @endif>Persetujuan</a>
                    @endif
                @elseif (auth('siswa')->check())
                    <a class="btn btn-outline-primary" href="{{ route('presensi.face') }}">Presensi wajah</a>
                    @if (Route::has('competition.student-consents.index'))
                        <a class="btn btn-outline-primary" href="{{ route('competition.student-consents.index') }}" @if (request()->routeIs('competition.student-consents.*')) aria-current="page" @endif>Persetujuan</a>
                    @endif
                @else
                    <a class="btn btn-outline-primary" href="{{ route('loginadmin') }}">Masuk sebagai admin</a>
                @endif
            </nav>
            <details class="competition-mobile-nav w-100">
                <summary class="btn btn-outline-primary" aria-label="Buka navigasi fitur lomba">Menu fitur lomba</summary>
                <nav class="competition-mobile-links" aria-label="Navigasi kompetisi seluler">
                    <a class="btn btn-outline-primary" href="{{ $competitionHomeUrl }}">{{ $competitionHomeLabel }}</a>
                    <a class="btn btn-outline-primary" href="{{ url('/competition') }}" @if (request()->routeIs('competition.home')) aria-current="page" @endif>Pusat lomba</a>
                    <a class="btn btn-outline-primary" href="{{ url('/competition/guide') }}" @if (request()->routeIs('competition.guide')) aria-current="page" @endif>Panduan juri</a>
                    @if (auth('user')->check())
                        @if (Route::has('competition.evaluation.index'))<a class="btn btn-outline-primary" href="{{ route('competition.evaluation.index') }}" @if (request()->routeIs('competition.evaluation.*')) aria-current="page" @endif>Evaluasi AI</a>@endif
                        @if (Route::has('competition.reviews.index'))<a class="btn btn-outline-primary" href="{{ route('competition.reviews.index') }}" @if (request()->routeIs('competition.reviews.*')) aria-current="page" @endif>Antrean review</a>@endif
                        @if (Route::has('competition.audit.index'))<a class="btn btn-outline-primary" href="{{ route('competition.audit.index') }}" @if (request()->routeIs('competition.audit.*')) aria-current="page" @endif>Log audit</a>@endif
                        @if (Route::has('competition.consents.index'))<a class="btn btn-outline-primary" href="{{ route('competition.consents.index') }}" @if (request()->routeIs('competition.consents.*')) aria-current="page" @endif>Persetujuan</a>@endif
                    @elseif (auth('siswa')->check())
                        <a class="btn btn-outline-primary" href="{{ route('presensi.face') }}" @if (request()->routeIs('presensi.face*')) aria-current="page" @endif>Presensi wajah</a>
                        @if (Route::has('competition.student-consents.index'))<a class="btn btn-outline-primary" href="{{ route('competition.student-consents.index') }}" @if (request()->routeIs('competition.student-consents.*')) aria-current="page" @endif>Persetujuan</a>@endif
                    @else
                        <a class="btn btn-outline-primary" href="{{ route('loginadmin') }}">Masuk sebagai admin</a>
                    @endif
                </nav>
            </details>
            <div class="competition-user-actions d-flex flex-wrap align-items-center gap-2 ms-md-auto">
                @if ($competitionUser)
                    <span class="badge bg-blue-lt text-blue competition-user-chip">{{ $competitionUser->name ?? $competitionUser->nama_lengkap }} · {{ $competitionRole }}</span>
                    <form method="POST" action="{{ route(auth('user')->check() ? 'proseslogoutadmin' : 'proseslogout') }}" class="m-0">
                        @csrf
                        <button class="btn btn-outline-secondary" type="submit">Keluar</button>
                    </form>
                @endif
            </div>
        </div>
    </header>
    <main id="competition-content" class="container-xl competition-main py-4">
        @if (! request()->routeIs('competition.home'))
            <nav aria-label="Jejak navigasi" class="mb-3">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ $competitionHomeUrl }}">{{ $competitionHomeLabel }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ url('/competition') }}">Pusat lomba</a></li>
                    <li class="breadcrumb-item active" aria-current="page">@yield('breadcrumb', trim($__env->yieldContent('title', 'Informasi')))</li>
                </ol>
            </nav>
        @endif
        @yield('content')
    </main>
</body>
</html>
