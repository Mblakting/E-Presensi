@extends('competition::layout')

@section('title', 'Antrean review')
@section('breadcrumb', 'Antrean review')

@section('content')
<div>
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title">Antrean Verifikasi Presensi</h1>
                <div class="text-secondary mt-1">Review challenge lama hanya mengubah catatan kompetisi. Antrean manual baru belum menulis presensi sampai admin menyetujui.</div>
            </div>
        </div>
    </div>
    <a class="btn btn-outline-secondary mt-3" href="{{ url('/competition') }}">Kembali ke Pusat Lomba</a>

    @if (session('status'))
        <div class="alert alert-success mt-3" role="status">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning mt-3" role="alert">{{ session('warning') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger mt-3" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card mt-3">
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>NISN</th>
                        <th>Waktu</th>
                        <th>Skor</th>
                        <th>Alasan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->siswa_nisn }}</td>
                            <td>{{ $item->created_at }}</td>
                            <td>{{ $item->score }}</td>
                            <td>{{ $item->reason }}</td>
                            <td><span class="badge {{ $item->status === 'pending' ? 'bg-yellow-lt text-dark' : ($item->status === 'approved' ? 'bg-green-lt text-green' : 'bg-red-lt text-red') }}">{{ match ($item->status) { 'pending' => 'Menunggu review', 'approved' => 'Disetujui', default => 'Ditolak' } }}</span></td>
                            <td>
                                @if ($item->status === 'pending')
                                    <form method="POST" action="{{ route('competition.reviews.decide', $item->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="decision" value="approved">
                                        <button class="btn btn-sm btn-success" type="submit">Setuju</button>
                                    </form>
                                    <form method="POST" action="{{ route('competition.reviews.decide', $item->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="decision" value="suspicious">
                                        <button class="btn btn-sm btn-danger" type="submit">Mencurigakan</button>
                                    </form>
                                @else
                                    <span class="text-secondary">Sudah ditinjau</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada item verifikasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $items->links() }}</div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">Antrean Verifikasi Manual (Trust Engine)</h3>
        </div>
        <div class="table-responsive">
            <table class="table card-table table-vcenter">
                <thead>
                    <tr>
                        <th>Nama siswa</th>
                        <th>Waktu percobaan</th>
                        <th>Skor Keputusan</th>
                        <th>Alasan</th>
                        <th>Foto Tangkapan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($manualItems as $manualItem)
                        <tr>
                            <td>{{ $manualItem->nama_lengkap }} <small class="text-secondary">({{ $manualItem->siswa_nisn }})</small></td>
                            <td>{{ $manualItem->attempted_at }}</td>
                            <td>{{ $manualItem->score }}</td>
                            <td>{{ $manualItem->reason }}</td>
                            <td>
                                <a href="{{ route('competition.reviews.manual.snapshot', $manualItem->id) }}" target="_blank" rel="noopener">Lihat foto</a>
                            </td>
                            <td><span class="badge {{ $manualItem->status === 'pending' ? 'bg-yellow-lt text-dark' : ($manualItem->status === 'approved' ? 'bg-green-lt text-green' : 'bg-red-lt text-red') }}">{{ match ($manualItem->status) { 'pending' => 'Menunggu review', 'approved' => 'Disetujui', default => 'Ditolak' } }}</span></td>
                            <td>
                                @if ($manualItem->status === 'pending')
                                    <form method="POST" action="{{ route('competition.reviews.manual.decide', $manualItem->id) }}" class="mb-1">
                                        @csrf
                                        <input type="hidden" name="decision" value="approved">
                                        <label class="visually-hidden" for="manual-note-{{ $manualItem->id }}">Catatan keputusan</label>
                                        <input id="manual-note-{{ $manualItem->id }}" class="form-control form-control-sm mb-1" name="catatan" maxlength="2000" placeholder="Catatan (opsional)">
                                        <button class="btn btn-sm btn-success" type="submit">Terima dan catat presensi</button>
                                    </form>
                                    <form method="POST" action="{{ route('competition.reviews.manual.decide', $manualItem->id) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="rejected">
                                        <label class="visually-hidden" for="manual-reject-{{ $manualItem->id }}">Alasan penolakan</label>
                                        <input id="manual-reject-{{ $manualItem->id }}" class="form-control form-control-sm mb-1" name="catatan" maxlength="2000" placeholder="Alasan penolakan (opsional)">
                                        <button class="btn btn-sm btn-danger" type="submit">Tolak</button>
                                    </form>
                                @else
                                    <span class="text-secondary">Sudah ditinjau</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada presensi yang menunggu verifikasi manual.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $manualItems->links() }}</div>
    </div>
</div>
@endsection
