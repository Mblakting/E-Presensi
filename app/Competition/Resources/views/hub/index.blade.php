@extends('competition::layout')

@section('title', 'Pusat Fitur Lomba')
@section('breadcrumb', 'Pusat fitur lomba')

@section('content')
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title competition-page-title">Pusat Fitur Lomba</h1>
                <p class="text-secondary mt-2 mb-0">Fitur tambahan kompetisi Bali DigiFest 2026. Aplikasi presensi utama tetap menjadi alur dasar.</p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
    @endif

    @if ($audience === 'admin')
        <div class="row row-cards">
            @foreach ($featureCards as $card)
                <div class="col-12 col-md-6">
                    <section class="card h-100">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                <h2 class="card-title mb-0">{{ $card['title'] }}</h2>
                                <span class="badge competition-status {{ $card['enabled'] ? 'bg-green-lt text-green' : 'bg-secondary-lt text-secondary' }}">
                                    {{ $card['enabled'] ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="text-secondary mt-3">{{ $card['description'] }}</p>
                            <p class="small competition-break mb-3">Flag: <code>{{ $card['flag'] }}</code></p>
                            @if ($card['count'] !== null)
                                <p class="mb-3"><strong>{{ $card['count_label'] }}:</strong> {{ $card['count'] }}</p>
                            @endif
                            @if ($card['url'])
                                <a class="btn btn-primary" href="{{ $card['url'] }}">Buka {{ $card['title'] }}</a>
                            @else
                                <span class="text-secondary">Halaman tidak dibuka karena fitur nonaktif.</span>
                            @endif
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
        <p class="text-secondary mt-4 mb-0">Jumlah yang ditampilkan hanya ringkasan agregat. Halaman pusat ini tidak menampilkan nama, NISN, koordinat, atau foto siswa.</p>
    @else
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Status persetujuan pemrosesan wajah</h2>
                <p class="mb-3">
                    <span class="badge {{ $consentStatus === 'Persetujuan aktif' ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">
                        {{ $consentStatus }}
                    </span>
                </p>
                @if ($studentConsentUrl)
                    <a class="btn btn-outline-primary me-2 mb-2" href="{{ $studentConsentUrl }}">Baca atau kelola persetujuan</a>
                @else
                    <p class="text-secondary">Fitur persetujuan belum diaktifkan. Hubungi pengelola untuk informasi.</p>
                @endif
                <a class="btn btn-primary mb-2" href="{{ $faceAttendanceUrl }}">Buka presensi wajah</a>
                <p class="text-secondary mt-2 mb-0">Jalur ini hanya untuk relawan dewasa. Jika Anda belum dewasa, minta orang tua/wali atau sekolah menyelesaikan proses tertulis yang sesuai.</p>
            </div>
        </section>
    @endif

    <a class="btn btn-outline-secondary" href="{{ url('/competition/guide') }}">Baca panduan juri dan batasan fitur</a>
@endsection
