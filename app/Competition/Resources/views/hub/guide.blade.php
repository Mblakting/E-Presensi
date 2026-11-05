@extends('competition::layout')

@section('title', 'Panduan juri')
@section('breadcrumb', 'Panduan juri')

@section('content')
    <h1 class="page-title competition-page-title mb-3">Panduan Juri</h1>
    <p class="text-secondary">Panduan ringkas untuk mencoba prototipe kompetisi. Halaman publik ini tidak memuat data siswa.</p>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Masalah</h2></div>
        <div class="card-body">
            <p class="mb-0">Presensi digital perlu menjaga alur tetap praktis sekaligus memberi cara menangani sinyal yang meragukan tanpa mengambil keputusan otomatis secara berlebihan.</p>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Solusi</h2></div>
        <div class="card-body">
            <p class="mb-0">E-Presensi mempertahankan aplikasi presensi yang sudah ada dan menambahkan lapisan kompetisi opsional: penilaian berbasis aturan, challenge liveness, antrean review manusia, serta dashboard evaluasi dan audit. Semua modul dikendalikan feature flag.</p>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">AI dan teknologi yang digunakan</h2></div>
        <div class="card-body">
            <ul class="mb-0">
                <li><strong>Trust scoring berbasis aturan:</strong> menggabungkan sinyal face distance yang dikirim browser, validasi geofence, konsistensi waktu, dan liveness. Ini bukan model machine learning terlatih.</li>
                <li><strong>Challenge-response liveness:</strong> challenge acak memakai nonce sekali pakai yang terikat sesi dan kedaluwarsa. Bukti gerakan diturunkan di browser, sehingga bukan bukti kriptografis dan belum membuktikan ketahanan terhadap spoofing.</li>
                <li><strong>Evaluasi FAR/FRR:</strong> dihitung dari percobaan uji genuine/print/screen yang benar-benar tercatat. Sampai data uji tersedia, dashboard menampilkan n=0 dan tidak mengklaim hasil performa.</li>
                <li><strong>Review manual:</strong> skor abu-abu dan fallback tertentu masuk antrean privat; presensi baru ditulis ke tabel klien setelah admin menyetujui. Review ini bukan verifikasi descriptor di server.</li>
            </ul>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Yang sudah ada dan yang ditambahkan</h2></div>
        <div class="card-body">
            <ul>
                <li><strong>Sudah ada (pre-existing):</strong> autentikasi, presensi GPS/foto, geofence, pengelolaan siswa, serta face recognition berbasis browser dengan face-api.js.</li>
                <li><strong>Ditambahkan untuk kompetisi:</strong> Trust Engine, liveness challenge, antrean review manual, pencatatan persetujuan, dashboard evaluasi, audit, serta pusat navigasi dan panduan.</li>
                <li>Rincian komponen dan data tersedia di <a href="https://github.com/Mblakting/E-Presensi/blob/digifest-2026-mvp/COMPETITION_DISCLOSURE.md" target="_blank" rel="noopener">COMPETITION_DISCLOSURE.md</a>.</li>
            </ul>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Cara mencoba</h2></div>
        <div class="card-body">
            <ol>
                <li>Masuk sebagai juri melalui halaman admin. Akun demo diberikan panitia melalui form/kanal privat; repository tidak menyediakan password.</li>
                <li>Untuk sisi siswa, masuk memakai akun sintetis yang disediakan panitia. Jangan gunakan akun atau data siswa nyata.</li>
                <li>Buka pusat fitur untuk melihat modul aktif/nonaktif dan langkah demo.</li>
                <li>Jika demo presensi wajah diizinkan, lakukan hanya pada data uji sintetis dan setelah proses persetujuan yang semestinya tersedia.</li>
                <li>Admin dapat meninjau antrean, evaluasi, log audit, serta status persetujuan ketika flag terkait aktif.</li>
            </ol>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="{{ route('loginadmin') }}">Masuk sebagai juri</a>
                <a class="btn btn-outline-primary" href="{{ route('login') }}">Masuk sebagai siswa</a>
                <a class="btn btn-outline-secondary" href="{{ url('/competition') }}">Pusat fitur lomba</a>
            </div>
        </div>
    </section>

    <section class="card mb-4">
        <div class="card-header"><h2 class="card-title">Keterbatasan yang harus diketahui</h2></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Jarak wajah/`confidence` masih dihitung dan dikirim browser; verifikasi jarak descriptor di server belum tersedia dan nilai browser dapat dipalsukan.</li>
                <li>Antrean review Phase 3b adalah human-in-the-loop, bukan verifikasi server-side.</li>
                <li>Sinyal liveness berasal dari browser. Resistensi terhadap foto cetak, layar, deepfake, atau injeksi kamera belum dibuktikan.</li>
                <li>Antrean privat menyimpan koordinat mentah, `confidence`, dan snapshot JPEG untuk keputusan admin; gunakan data sintetis saja.</li>
                <li>Jika evaluasi gagal ketika enforce aktif, presensi dapat diblokir. Admin harus menonaktifkan flag enforce sebagai rencana cadangan demo.</li>
            </ul>
        </div>
    </section>

    <p class="mb-4">Panduan operasional lengkap tersedia pada <a href="https://github.com/Mblakting/E-Presensi/blob/digifest-2026-mvp/docs/JUDGE_GUIDE.md" target="_blank" rel="noopener">Panduan Juri</a>.</p>
    <a class="btn btn-outline-secondary me-2" href="{{ url('/competition') }}">Kembali ke Pusat Lomba</a>
    <a class="btn btn-outline-secondary" href="{{ route('login') }}">Kembali ke halaman masuk</a>
@endsection
