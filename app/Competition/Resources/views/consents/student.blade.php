@extends('competition::layout')

@section('title', 'Persetujuan Wajah')
@section('breadcrumb', 'Persetujuan siswa')

@section('content')
    <h1 class="page-title competition-page-title mb-3">Persetujuan Pemrosesan Wajah</h1>
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">Status akun Anda</h2>
            <p><span class="badge {{ $consentStatus === 'Persetujuan aktif' ? 'bg-green-lt text-green' : 'bg-yellow-lt text-yellow' }}">{{ $consentStatus }}</span></p>
            <p class="text-secondary mb-0">Versi teks: {{ $version }}. Status ini hanya untuk akun yang sedang masuk.</p>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Informasi persetujuan</h2></div>
        <div class="card-body">
            @if ($textApproved)
                <div class="alert alert-info"><strong>Teks ditinjau dan disetujui.</strong></div>
            @else
                <div class="alert alert-warning"><strong>DRAF — belum dapat digunakan.</strong> Admin harus meninjau teks dan mengisi kontak pengelola sebelum persetujuan dapat dicatat.</div>
            @endif
            <div class="competition-break" style="white-space: pre-wrap">{{ $consentText }}</div>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-body">
            <h2 class="card-title">Pilihan relawan dewasa</h2>
            <p>Gunakan tombol setuju hanya jika Anda berusia 18 tahun atau lebih, telah membaca teks di atas, dan memilih untuk ikut secara sukarela. Jika Anda belum dewasa, jangan gunakan persetujuan ini; minta orang tua/wali atau sekolah menyelesaikan proses tertulis yang sesuai melalui pengelola.</p>
            <form method="POST" action="{{ route('competition.student-consents.grant') }}">
                @csrf
                <div class="form-check mb-3">
                    <input class="form-check-input" id="adult-volunteer" type="checkbox" name="adult_volunteer_verified" value="1" required {{ $textApproved ? '' : 'disabled' }}>
                    <label class="form-check-label" for="adult-volunteer">Saya menyatakan berusia 18 tahun atau lebih, telah membaca teks, dan secara sukarela menyetujui pemrosesan yang dijelaskan.</label>
                </div>
                <button class="btn btn-primary" type="submit" {{ $textApproved ? '' : 'disabled' }}>Saya setuju sebagai relawan dewasa</button>
            </form>
            @if (! $textApproved)
                <p class="text-danger mt-3 mb-0">Belum bisa mencatat persetujuan karena teks masih draf.</p>
            @endif
        </div>
    </section>

    @if ($latestStatus === 'granted')
        <form method="POST" action="{{ route('competition.student-consents.withdraw') }}" class="mb-4">
            @csrf
            <button class="btn btn-outline-danger" type="submit">Tarik persetujuan saya</button>
        </form>
    @endif
    <a class="btn btn-outline-secondary" href="{{ url('/competition') }}">Kembali ke pusat lomba</a>
    <a class="btn btn-outline-primary" href="{{ url('/presensi/face') }}">Kembali ke presensi wajah</a>
@endsection
