@extends('layouts.admin.app')

@section('content')
<div class="page-header d-print-none">
    <div class="container-xl">
        <div class="row g-2 align-items-center">
            <div class="col">
                <h2 class="page-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-2 text-primary"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 8v-2a2 2 0 0 1 2 -2h2" /><path d="M4 16v2a2 2 0 0 0 2 2h2" /><path d="M16 4h2a2 2 0 0 1 2 2v2" /><path d="M16 20h2a2 2 0 0 0 2 -2v-2" /><path d="M9 10a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M13 10a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M9.5 15a3.5 3.5 0 0 0 5 0" /></svg>
                    Batch ID Muka Siswa (Bulk Upload)
                </h2>
                <p class="text-muted mt-1">Daftarkan wajah banyak siswa sekaligus tanpa scan webcam satu per satu</p>
            </div>
            <div class="col-auto">
                <a href="{{ route('siswa.index') }}" class="btn btn-outline-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0" /><path d="M5 12l6 6" /><path d="M5 12l6 -6" /></svg>
                    Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="container-xl">
        <div class="row">

            {{-- Panel Kiri: Upload & Panduan --}}
            <div class="col-lg-4">

                {{-- Status Model AI --}}
                <div class="card mb-3" id="cardModelStatus">
                    <div class="card-body">
                        <div id="modelStatus" class="d-flex align-items-center text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <span>Memuat model AI face recognition...</span>
                        </div>
                    </div>
                </div>

                {{-- Form Upload ZIP --}}
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Upload File ZIP</h3>
                    </div>
                    <div class="card-body">
                        <form id="frmUploadZip" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">File ZIP Foto Siswa <span class="text-danger">*</span></label>
                                <input type="file" name="file_zip" id="fileZip" class="form-control" accept=".zip">
                                <small class="text-muted">Format: .zip | Maks. 50MB</small>
                            </div>
                            <button type="submit" id="btnExtract" class="btn btn-primary w-100" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                                Ekstrak & Proses Wajah
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Panduan --}}
                <div class="card mt-3">
                    <div class="card-header"><h4 class="card-title">Panduan Format ZIP</h4></div>
                    <div class="card-body">
                        <p class="text-muted small mb-2">Isi file ZIP dengan foto siswa. Nama file = NISN siswa:</p>
                        <div class="bg-light rounded p-2 mb-2" style="font-family:monospace; font-size:0.8rem;">
                            📁 foto_siswa.zip<br>
                            &nbsp;&nbsp;├── 0012345678.jpg<br>
                            &nbsp;&nbsp;├── 0087654321.png<br>
                            &nbsp;&nbsp;└── 0011223344.jpg
                        </div>
                        <ul class="text-muted small mb-0">
                            <li>Format foto: JPG / PNG</li>
                            <li>1 wajah per foto, pencahayaan cukup</li>
                            <li>Foto dengan NISN tidak terdaftar akan dilewati</li>
                            <li>Foto lama akan otomatis diganti</li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Panel Kanan: Progress & Hasil --}}
            <div class="col-lg-8">

                {{-- Progress --}}
                <div class="card mb-3" id="cardProgress" style="display:none !important;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold" id="progressLabel">Memproses...</span>
                            <span id="progressCount" class="text-muted">0 / 0</span>
                        </div>
                        <div class="progress mb-2" style="height:12px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                 role="progressbar" style="width:0%"></div>
                        </div>
                        <small id="progressDetail" class="text-muted"></small>
                    </div>
                </div>

                {{-- Ringkasan Hasil --}}
                <div class="card mb-3" id="cardResult" style="display:none !important;">
                    <div class="card-header"><h3 class="card-title">Ringkasan Hasil</h3></div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="text-success fw-bold" style="font-size:2rem;" id="countSuccess">0</div>
                                <div class="text-muted">✅ Berhasil</div>
                            </div>
                            <div class="col-6">
                                <div class="text-danger fw-bold" style="font-size:2rem;" id="countFail">0</div>
                                <div class="text-muted">❌ Gagal</div>
                            </div>
                        </div>
                        <button id="btnReset" class="btn btn-outline-secondary w-100 mt-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg>
                            Proses Batch Baru
                        </button>
                    </div>
                </div>

                {{-- Log Detail --}}
                <div class="card" id="cardLog" style="display:none !important;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Log Proses</h4>
                        <span id="logBadge" class="badge bg-secondary">0 entri</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="logContainer" style="max-height:350px; overflow-y:auto; font-size:0.85rem;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>NISN</th>
                                        <th>Nama</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="logBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Canvas tersembunyi untuk processing --}}
                <canvas id="hiddenCanvas" style="display:none;"></canvas>
                <img id="hiddenImg" style="display:none;" crossorigin="anonymous">

            </div>
        </div>
    </div>
</div>
@endsection

@push('myscript')
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
const MODEL_URL   = "{{ asset('face-models') }}";
const EXTRACT_URL = "{{ route('batch.face.extract') }}";
const SAVE_URL    = "{{ route('face.save') }}";
const CLEANUP_URL = "{{ route('batch.face.cleanup') }}";
const CSRF_TOKEN  = "{{ csrf_token() }}";

let modelsReady = false;

// ── Load Model AI ──────────────────────────────────────────────────────────
async function loadModels() {
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        modelsReady = true;
        document.getElementById('modelStatus').innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-2 text-success"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>' +
            '<span class="text-success fw-bold">Model AI siap! Silakan upload file ZIP.</span>';
        document.getElementById('btnExtract').disabled = false;
    } catch (err) {
        document.getElementById('modelStatus').innerHTML =
            '<span class="text-danger">❌ Gagal memuat model AI: ' + err.message + '</span>';
    }
}

// ── Enable button saat file dipilih ───────────────────────────────────────
document.getElementById('fileZip').addEventListener('change', function() {
    document.getElementById('btnExtract').disabled = !(modelsReady && this.files.length > 0);
});

// ── Submit: Extract ZIP ────────────────────────────────────────────────────
document.getElementById('frmUploadZip').addEventListener('submit', async function(e) {
    e.preventDefault();

    const fileInput = document.getElementById('fileZip');
    if (!fileInput.files.length) return;

    const btn = document.getElementById('btnExtract');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengekstrak ZIP...';

    const formData = new FormData();
    formData.append('_token', CSRF_TOKEN);
    formData.append('file_zip', fileInput.files[0]);

    try {
        const res  = await fetch(EXTRACT_URL, { method: 'POST', body: formData });
        const json = await res.json();

        if (json.status !== 'success') {
            Swal.fire('Gagal', json.message, 'error');
            btn.disabled = false;
            btn.innerHTML = 'Ekstrak & Proses Wajah';
            return;
        }

        await processBatch(json.data);

    } catch (err) {
        Swal.fire('Error', 'Gagal menghubungi server: ' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = 'Ekstrak & Proses Wajah';
    }
});

// ── Proses Batch Face Detection ────────────────────────────────────────────
async function processBatch(items) {
    const total      = items.length;
    let   success    = 0;
    let   fail       = 0;
    const logBody    = document.getElementById('logBody');

    // Tampilkan panel progress & log
    showPanel('cardProgress');
    showPanel('cardLog');
    logBody.innerHTML = '';

    const canvas = document.getElementById('hiddenCanvas');
    const ctx    = canvas.getContext('2d');
    const img    = document.getElementById('hiddenImg');

    for (let i = 0; i < total; i++) {
        const item = items[i];
        updateProgress(i + 1, total, `Memproses: ${item.nama} (${item.nisn})`);

        try {
            // Load gambar ke canvas tersembunyi
            await loadImage(img, item.url);
            canvas.width  = img.naturalWidth;
            canvas.height = img.naturalHeight;
            ctx.drawImage(img, 0, 0);

            // Deteksi wajah
            const detection = await faceapi
                .detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                fail++;
                addLog(logBody, item.nisn, item.nama, 'error', 'Wajah tidak terdeteksi di foto');
                continue;
            }

            const descriptor = Array.from(detection.descriptor);

            // Ambil snapshot foto dari canvas sebagai base64
            const fotoSnapshot = canvas.toDataURL('image/jpeg', 0.85);

            // Simpan ke server (reuse endpoint yang sudah ada)
            const saveRes = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ nisn: item.nisn, descriptor, foto_snapshot: fotoSnapshot }),
            });
            const saveJson = await saveRes.json();

            if (saveJson.status === 'success') {
                success++;
                addLog(logBody, item.nisn, item.nama, 'success', 'ID Muka berhasil dibuat');
            } else {
                fail++;
                addLog(logBody, item.nisn, item.nama, 'error', saveJson.message || 'Gagal menyimpan');
            }

        } catch (err) {
            fail++;
            addLog(logBody, item.nisn, item.nama, 'error', err.message);
        }
    }

    // Selesai — cleanup temp folder
    await fetch(CLEANUP_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
    }).catch(() => {});

    // Tampilkan ringkasan
    finishBatch(success, fail, total);
}

// ── Helper: Load image ke element ─────────────────────────────────────────
function loadImage(imgEl, url) {
    return new Promise((resolve, reject) => {
        imgEl.onload  = () => resolve();
        imgEl.onerror = () => reject(new Error('Gagal memuat gambar'));
        imgEl.src = url + '?t=' + Date.now(); // cache-bust
    });
}

// ── Helper: Update progress bar ───────────────────────────────────────────
function updateProgress(current, total, detail) {
    const pct = Math.round((current / total) * 100);
    document.getElementById('progressBar').style.width   = pct + '%';
    document.getElementById('progressCount').textContent = current + ' / ' + total;
    document.getElementById('progressLabel').textContent = 'Memproses foto...';
    document.getElementById('progressDetail').textContent = detail;
}

// ── Helper: Tambah baris log ───────────────────────────────────────────────
function addLog(tbody, nisn, nama, type, msg) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><code>${nisn}</code></td>
        <td>${nama}</td>
        <td>${type === 'success'
            ? '<span class="badge bg-success">✅ Berhasil</span>'
            : '<span class="badge bg-danger">❌ Gagal</span>'}</td>
        <td class="text-muted">${msg}</td>`;
    tbody.appendChild(tr);

    // Update badge count
    const count = tbody.querySelectorAll('tr').length;
    document.getElementById('logBadge').textContent = count + ' entri';

    // Auto-scroll
    const container = document.getElementById('logContainer');
    container.scrollTop = container.scrollHeight;
}

// ── Helper: Selesai ────────────────────────────────────────────────────────
function finishBatch(success, fail, total) {
    document.getElementById('progressLabel').textContent = '✅ Selesai!';
    document.getElementById('progressBar').classList.remove('progress-bar-animated');
    document.getElementById('progressBar').classList.add('bg-success');
    document.getElementById('progressBar').style.width = '100%';
    document.getElementById('progressDetail').textContent =
        `${success} berhasil, ${fail} gagal dari ${total} foto.`;

    document.getElementById('countSuccess').textContent = success;
    document.getElementById('countFail').textContent    = fail;
    showPanel('cardResult');

    if (fail > 0) {
        Swal.fire({
            title: 'Proses Selesai',
            html: `<b>${success}</b> siswa berhasil dibuatkan ID Muka.<br><b>${fail}</b> foto gagal diproses.<br><small class="text-muted">Lihat log di bawah untuk detail.</small>`,
            icon: success > 0 ? 'success' : 'warning',
        });
    } else {
        Swal.fire('Selesai!', `${success} siswa berhasil dibuatkan ID Muka.`, 'success');
    }
}

// ── Helper: Tampilkan panel ────────────────────────────────────────────────
function showPanel(id) {
    const el = document.getElementById(id);
    el.style.removeProperty('display');
    el.style.display = 'block';
}

// ── Reset ──────────────────────────────────────────────────────────────────
document.getElementById('btnReset').addEventListener('click', function() {
    document.getElementById('frmUploadZip').reset();
    document.getElementById('logBody').innerHTML = '';
    document.getElementById('logBadge').textContent = '0 entri';
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('progressBar').className = 'progress-bar progress-bar-striped progress-bar-animated bg-primary';
    ['cardProgress', 'cardResult', 'cardLog'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    document.getElementById('btnExtract').disabled = !modelsReady;
    document.getElementById('btnExtract').innerHTML =
        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-1"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg> Ekstrak & Proses Wajah';
});

// Mulai load model
loadModels();
</script>
@endpush
