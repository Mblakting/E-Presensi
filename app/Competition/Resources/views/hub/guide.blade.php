@extends('competition::layout')

@section('title', 'Panduan Juri')
@section('breadcrumb', 'Panduan juri')

@section('content')
    <h1 class="page-title competition-page-title mb-3">Panduan Juri</h1>
    <p class="text-secondary">Panduan singkat untuk mencoba fitur kompetisi tanpa menyamakan fitur berbasis aturan dengan model AI.</p>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Yang sudah ada dan yang ditambahkan</h2></div>
        <div class="card-body">
            <ul class="mb-0">
                <li><strong>Pre-existing:</strong> presensi GPS/foto, pendaftaran dan pencocokan wajah di browser, geofence Haversine, serta manajemen siswa.</li>
                <li><strong>Fitur kompetisi:</strong> Trust Engine berbasis aturan, challenge liveness dengan bukti turunan dari browser, antrean review manual human-in-the-loop, consent ledger, dashboard evaluasi, dan audit sesuai feature flag.</li>
                <li>Rincian pemisahan dan pengungkapan data ada di <a href="https://github.com/Mblakting/E-Presensi/blob/digifest-2026-mvp/COMPETITION_DISCLOSURE.md" target="_blank" rel="noopener">COMPETITION_DISCLOSURE.md</a>.</li>
            </ul>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Urutan mencoba</h2></div>
        <div class="card-body">
            <ol class="mb-0">
                <li>Masuk sebagai siswa melalui <a href="{{ route('login') }}">halaman login siswa</a> (<code>/</code>), atau sebagai admin melalui <a href="{{ route('loginadmin') }}">halaman login admin</a> (<code>/panel</code>).</li>
                <li>Buka <a href="{{ url('/competition') }}">Pusat Fitur Lomba</a>; admin melihat status modul, siswa melihat status persetujuannya.</li>
                <li>Untuk presensi wajah, gunakan <a href="{{ url('/presensi/face') }}">/presensi/face</a>. Consent harus diaktifkan dan teksnya disetujui sebelum persetujuan dapat dicatat.</li>
                <li>Jika enforce telah diaktifkan untuk uji terkontrol, skor menengah masuk ke <code>/competition/reviews</code> dan menunggu keputusan admin. Jangan aktifkan sebelum uji dan persetujuan siap.</li>
                <li>Dashboard evaluasi ada di <code>/competition/ai-evaluation</code>; hasil kosong (<code>n=0</code>) berarti belum ada uji manual yang diimpor.</li>
                <li>Log audit tersedia di <code>/competition/audit</code> bila flag audit aktif.</li>
            </ol>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Batasan yang perlu diketahui</h2></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Face distance/`confidence` masih dihitung dan dikirim browser; verifikasi jarak descriptor di server belum tersedia dan nilai browser dapat dipalsukan.</li>
                <li>Phase 3b adalah antrean review manual, bukan verifikasi wajah server-side. Presensi antrean baru ditulis setelah admin menyetujui.</li>
                <li>Sinyal liveness berasal dari browser dan bukan bukti kriptografis. Resistensi terhadap foto cetak, layar, atau injeksi kamera belum dibuktikan.</li>
                <li>Jangan gunakan data siswa nyata untuk demo publik. Gunakan akun dan data sintetis yang disediakan penyelenggara.</li>
            </ul>
        </div>
    </section>
    <a class="btn btn-outline-primary" href="{{ url('/') }}">Kembali ke login siswa</a>
    <a class="btn btn-outline-secondary" href="{{ url('/panel') }}">Login admin</a>
@endsection
