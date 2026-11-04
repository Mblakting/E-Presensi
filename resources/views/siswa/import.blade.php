@extends('layouts.admin.app')
@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">Import Data Siswa (CSV)</h2>
            </div>
            <div class="col-auto">
                <a href="{{ route('siswa.index') }}" class="btn btn-outline-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M5 12l6 6" /><path d="M5 12l6 -6" /></svg>
                    Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-lg-7">

                @if(session('success'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if(session('warning'))
                <div class="alert alert-warning alert-dismissible" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.871l-8.106 -13.534a1.914 1.914 0 0 0 -3.274 0z" /><path d="M12 16h.01" /></svg>
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Unggah File CSV</h3>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('siswa.import') }}" method="POST" enctype="multipart/form-data" id="frmImport">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">File CSV <span class="text-danger">*</span></label>
                                <input type="file" name="file_csv" class="form-control @error('file_csv') is-invalid @enderror" accept=".csv,.txt">
                                @error('file_csv')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Format: .csv | Maks. 2MB</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100" id="btnImport">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                                Import Sekarang
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Format CSV yang Dibutuhkan</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-2">Pastikan file CSV memiliki header berikut di baris pertama:</p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>nama</th>
                                        <th>nisn</th>
                                        <th>kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Budi Santoso</td>
                                        <td>0012345678</td>
                                        <td>XII TKJ 1</td>
                                    </tr>
                                    <tr>
                                        <td>Siti Rahayu</td>
                                        <td>0087654321</td>
                                        <td>XI RPL 2</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <ul class="text-muted small mt-2 mb-0">
                            <li>Password default semua siswa baru: <strong>12345</strong></li>
                            <li>Baris dengan NISN yang sudah ada akan dilewati (tidak duplikat)</li>
                            <li>Kolom tambahan selain 3 kolom di atas akan diabaikan</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('myscript')
<script>
    document.getElementById('frmImport').addEventListener('submit', function() {
        var btn = document.getElementById('btnImport');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
    });
</script>
@endpush
