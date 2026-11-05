@extends('competition::layout')

@section('title', 'Pusat Fitur Lomba')

@section('content')
    <header class="mb-4">
        <h1 class="page-title competition-page-title">Pusat Fitur Lomba</h1>
        <p class="text-secondary mt-2 mb-0">Fitur tambahan Bali DigiFest 2026. Aplikasi presensi utama tetap menjadi alur dasar.</p>
    </header>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
    @endif

    @if (($audience ?? '') === 'admin')
        @if ($consentGateEnabled && ! $consentTextApproved)
            <div class="alert alert-danger" role="alert">
                <strong>Perhatian: presensi wajah diblokir.</strong>
                <p class="mb-0">Presensi wajah diblokir untuk semua siswa karena teks persetujuan belum disetujui. Setujui teks (COMPETITION_CONSENT_TEXT_APPROVED=true) atau matikan FEATURE_COMPETITION_CONSENT.</p>
            </div>
        @endif

        <div class="row row-cards">
            @foreach ($featureCards as $card)
                <div class="col-12 col-md-6">
                    <section class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                <h2 class="card-title mb-0">{{ $card['title'] }}</h2>
                                <span class="badge competition-status {{ $card['enabled'] ? 'bg-green-lt text-green' : 'bg-secondary-lt text-dark' }}">
                                    <span aria-hidden="true">{{ $card['enabled'] ? '✓' : '–' }}</span>
                                    {{ $card['enabled'] ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="text-secondary mt-3">{{ $card['description'] }}</p>
                            @if ($card['count'] !== null)
                                <p class="mb-3"><strong>{{ $card['count_label'] }}:</strong> {{ $card['count'] }}</p>
                            @endif
                            <div class="mt-auto">
                                @if ($card['url'])
                                    <a class="btn btn-primary" href="{{ $card['url'] }}">Buka {{ $card['title'] }}</a>
                                @else
                                    <button class="btn btn-outline-secondary" type="button" disabled>Belum tersedia</button>
                                    <p class="small text-secondary mt-2 mb-0">{{ $card['disabled_reason'] }}</p>
                                @endif
                                <details class="mt-2">
                                    <summary class="text-secondary">Detail teknis</summary>
                                    <p class="small competition-break mt-2 mb-0">Flag: <code>{{ $card['flag'] }}</code></p>
                                </details>
                            </div>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>
        <p class="text-secondary mt-4 mb-0">Angka hanya ringkasan agregat. Pusat lomba tidak menampilkan nama, NISN, koordinat, atau foto siswa.</p>
    @else
        <section class="card mb-4">
            <div class="card-body">
                <h2 class="card-title">Status persetujuan pemrosesan wajah</h2>
                <p class="mb-3">
                    <span class="badge {{ $consentStatus === 'Persetujuan aktif' ? 'bg-green-lt text-green' : 'bg-yellow-lt text-dark' }}">
                        {{ $consentStatus }}
                    </span>
                </p>
                @if ($studentConsentUrl)
                    <a class="btn btn-outline-primary me-2 mb-2" href="{{ $studentConsentUrl }}">Baca atau kelola persetujuan</a>
                @else
                    <p class="text-secondary">Fitur persetujuan belum diaktifkan. Hubungi pengelola untuk informasi.</p>
                @endif
                <a class="btn btn-primary mb-2" href="{{ $faceAttendanceUrl }}">Buka presensi wajah</a>
                <a class="btn btn-outline-secondary mb-2" href="{{ route('presensi.create') }}">Gunakan presensi non-wajah</a>
                <p class="text-secondary mt-2 mb-0">Jalur ini hanya untuk relawan dewasa. Jika Anda belum dewasa, minta orang tua/wali atau sekolah menyelesaikan proses tertulis yang sesuai.</p>
            </div>
        </section>
    @endif

    @if (($audience ?? '') === 'admin')
        <section class="card mb-4">
            <div class="card-header"><h2 class="card-title">Alur demo juri</h2></div>
            <div class="card-body">
                <ol class="list-unstyled mb-0">
                    @foreach ($demoFlow as $step)
                        <li class="d-flex flex-wrap align-items-center gap-2 mb-3">
                            <span class="badge bg-blue-lt text-blue">{{ $loop->iteration }}</span>
                            @if ($step['url'])
                                <a class="btn btn-outline-primary" href="{{ $step['url'] }}">{{ $step['label'] }}</a>
                            @else
                                <button class="btn btn-outline-secondary" type="button" disabled>{{ $step['label'] }}</button>
                                <span class="small text-secondary">{{ $step['reason'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <p class="mb-4">Panduan menjelaskan fitur yang sudah ada, fitur kompetisi, dan keterbatasannya.</p>
    <a class="btn btn-outline-primary" href="{{ url('/competition/guide') }}">Baca panduan juri</a>

    <footer class="border-top mt-5 pt-3 text-secondary small">
        <p class="mb-1">AI Web Innovation Challenge — Bali DigiFest 2026</p>
        <p class="mb-0">
            <a href="https://github.com/Mblakting/E-Presensi/blob/digifest-2026-mvp/README.md" target="_blank" rel="noopener">README proyek</a>
            @if (config('competition.version'))
                · Versi {{ config('competition.version') }}
            @endif
            @if (config('competition.commit'))
                · Commit {{ \Illuminate\Support\Str::limit((string) config('competition.commit'), 8, '') }}
            @endif
        </p>
    </footer>
@endsection
