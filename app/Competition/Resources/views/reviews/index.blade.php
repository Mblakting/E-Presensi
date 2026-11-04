@extends('layouts.admin.app')

@section('content')
<div class="container-xl">
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">Antrean Verifikasi Presensi</h2>
                <div class="text-secondary mt-1">Keputusan di sini hanya mengubah catatan kompetisi, bukan data presensi.</div>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success mt-3">{{ session('status') }}</div>
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
                            <td>{{ ucfirst($item->status) }}</td>
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
</div>
@endsection
