@extends('layouts.admin.app')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-3">Audit Log Kompetisi</h1>
    <p>Daftar baca-saja; pembukaan halaman audit juga dicatat. IP dan user-agent tidak ditampilkan.</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>Waktu</th><th>Peristiwa</th><th>Aktor</th><th>Subjek</th><th>Sintetis</th></tr></thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->created_at }}</td>
                            <td>{{ $event->event_type }}</td>
                            <td>{{ $event->actor_type }} {{ $event->actor_id }}</td>
                            <td>{{ $event->subject_nisn ? '…'.substr($event->subject_nisn, -4) : '—' }}</td>
                            <td>{{ $event->is_synthetic ? 'Ya' : 'Tidak' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum ada event audit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $events->links() }}</div>
    </div>
</div>
@endsection
