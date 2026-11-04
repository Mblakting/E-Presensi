@extends('layouts.admin.app')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-3">Persetujuan Pemrosesan Wajah</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="alert {{ $textApproved ? 'alert-info' : 'alert-warning' }}">
        <strong>{{ $textApproved ? 'Teks disetujui' : 'DRAF — belum boleh digunakan' }}</strong>
        <p class="mb-1">Versi {{ $version }} · SHA-256 {{ $textHash }}</p>
        <pre class="mb-0" style="white-space: pre-wrap">{{ $text }}</pre>
    </div>
    <p>Gunakan hanya setelah teks ditinjau. Untuk siswa di bawah umur, verifikasi proses tertulis sekolah atau wali. Jangan mengunggah dokumen, foto, atau descriptor sebagai bukti persetujuan.</p>

    <div class="card mb-4">
        <div class="card-header">Catat persetujuan atau penarikan</div>
        <div class="card-body">
            <form method="POST" action="{{ route('competition.consents.grant', ['nisn' => '__NISN__']) }}" id="grant-consent-form">
                @csrf
                <div class="form-group">
                    <label for="grant-nisn">NISN</label>
                    <input class="form-control" id="grant-nisn" name="nisn" required>
                </div>
                <div class="form-group">
                    <label for="authority-basis">Dasar verifikasi</label>
                    <select class="form-control" id="authority-basis" name="authority_basis" required>
                        <option value="adult_volunteer">Relawan dewasa</option>
                        <option value="school_guardian_record">Catatan tertulis sekolah/wali</option>
                    </select>
                </div>
                <label><input type="checkbox" name="authority_verified" value="1" required> Saya telah memverifikasi proses persetujuan tertulis yang sesuai.</label>
                <button class="btn btn-primary" type="submit" {{ $textApproved ? '' : 'disabled' }}>Catat persetujuan</button>
                @if (! $textApproved)
                    <p class="text-danger mt-2 mb-0">Aksi dinonaktifkan sampai draf ditinjau dan teks disetujui melalui perubahan konfigurasi yang ditinjau.</p>
                @endif
            </form>
            <form method="POST" action="{{ route('competition.consents.withdraw', ['nisn' => '__NISN__']) }}" id="withdraw-consent-form" class="mt-3">
                @csrf
                <label for="withdraw-nisn">NISN untuk menarik persetujuan</label>
                <div class="input-group">
                    <input class="form-control" id="withdraw-nisn" name="nisn" required>
                    <button class="btn btn-outline-danger" type="submit">Tarik persetujuan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Status terkini</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>NISN</th><th>Status</th><th>Versi</th><th>Dicatat</th><th>Oleh</th></tr></thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->siswa_nisn }}</td>
                            <td>{{ $record->status === 'granted' ? 'Aktif' : 'Ditarik' }}</td>
                            <td>{{ $record->text_version }}</td>
                            <td>{{ $record->recorded_at }}</td>
                            <td>{{ $record->actor_type }} {{ $record->actor_id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum ada catatan persetujuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $records->links() }}</div>
    </div>
</div>
<script>
    document.getElementById('grant-consent-form').addEventListener('submit', function (event) {
        event.preventDefault();
        this.action = this.action.replace('__NISN__', encodeURIComponent(document.getElementById('grant-nisn').value));
        this.submit();
    });
    document.getElementById('withdraw-consent-form').addEventListener('submit', function (event) {
        event.preventDefault();
        this.action = this.action.replace('__NISN__', encodeURIComponent(document.getElementById('withdraw-nisn').value));
        this.submit();
    });
</script>
@endsection
