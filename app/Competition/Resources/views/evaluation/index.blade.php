@extends('competition::layout')

@section('title', 'Evaluasi AI')
@section('breadcrumb', 'Evaluasi AI')

@section('content')
<div>
    <h1 class="h3 mb-3">Evaluasi AI — Bukti Keandalan dan Privasi</h1>
    <p>Data hanya berasal dari challenge dan percobaan manual yang benar-benar tercatat. Halaman ini hanya-baca.</p>
    <a class="btn btn-outline-secondary mb-3" href="{{ url('/competition') }}">Kembali ke Pusat Lomba</a>

    <div class="card mb-4">
        <div class="card-header">Rekaman challenge kompetisi</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead><tr><th>Verdict</th><th>Jumlah</th></tr></thead>
                <tbody>
                    @forelse ($verdicts as $verdict => $total)
                        <tr><td>{{ $verdict ?? 'belum ada verdict' }}</td><td>{{ $total }}</td></tr>
                    @empty
                        <tr><td colspan="2">Belum ada data challenge.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Status liveness dan alasan penolakan</div>
        <div class="card-body">
            <h2 class="h4">Status liveness</h2>
            <ul>
                @forelse ($liveness as $status => $total)
                    <li>{{ $status ?? 'belum diperiksa' }}: {{ $total }}</li>
                @empty
                    <li>Belum ada data liveness.</li>
                @endforelse
            </ul>
            <h2 class="h4">Alasan penolakan</h2>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Kode</th><th>Alasan</th><th>Jumlah</th></tr></thead>
                    <tbody>
                        @forelse ($rejectionReasons as $entry)
                            <tr><td>{{ $entry->reason_code }}</td><td>{{ $entry->reason }}</td><td>{{ $entry->total }}</td></tr>
                        @empty
                            <tr><td colspan="3">Belum ada penolakan tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Uji manual (n = {{ $trialCount }})</div>
        <div class="card-body">
            @if ($trialCount === 0)
                <div class="alert alert-info" role="status">Belum ada percobaan uji manual yang tercatat (n=0). Impor hasil pengujian anonim yang benar-benar dilakukan untuk melihat FAR/FRR. Tidak ada angka performa yang dapat dilaporkan saat ini.</div>
            @elseif ($trialCount < $smallSampleThreshold)
                <div class="alert alert-warning">Sampel kecil ({{ $trialCount }} percobaan; peringatan berlaku di bawah {{ $smallSampleThreshold }}). Angka ini hanya deskriptif dan bukan jaminan keamanan.</div>
            @endif
            <p><strong>Metode:</strong> genuine = wajah relawan dewasa yang setuju; print = foto cetak; screen = foto pada layar. n adalah seluruh percobaan untuk jenis tersebut. Review ditampilkan terpisah dan tidak masuk denominator. Denominator FAR/FRR adalah accepted + rejected.</p>
            <p>FRR = genuine yang ditolak / genuine yang diputuskan. FAR = serangan yang diterima / percobaan serangan yang diputuskan. Tingkat penolakan serangan = serangan yang ditolak / percobaan serangan yang diputuskan. Print dan screen dihitung terpisah. Nilai tanpa denominator ditampilkan sebagai —.</p>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Jenis</th><th>n</th><th>Accepted</th><th>Rejected</th><th>Review</th><th>Denominator</th><th>FRR</th><th>FAR</th><th>Serangan ditolak</th></tr></thead>
                    <tbody>
                        @foreach (['genuine' => 'Wajah asli', 'print' => 'Foto cetak', 'screen' => 'Foto layar'] as $type => $label)
                            @php($metrics = $trials[$type])
                            <tr>
                                <td>{{ $label }}</td><td>{{ $metrics['n'] }}</td><td>{{ $metrics['accepted'] }}</td><td>{{ $metrics['rejected'] }}</td><td>{{ $metrics['review'] }}</td><td>{{ $metrics['n_decided'] }}</td>
                                <td>{{ $metrics['frr'] === null ? '—' : $metrics['frr'].'%' }}</td>
                                <td>{{ $metrics['far'] === null ? '—' : $metrics['far'].'%' }}</td>
                                <td>{{ $metrics['attack_rejection_rate'] === null ? '—' : $metrics['attack_rejection_rate'].'%' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 mb-0">Catatan uji dianonimkan dan tidak boleh memuat nama, NISN, foto, descriptor, atau kredensial. Sampel sukarela yang kecil tidak mewakili kinerja populasi umum.</p>
        </div>
    </div>
</div>
@endsection
