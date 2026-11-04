# Panduan Uji Manual Phase 3a

Uji hanya pada instance lomba dengan data siswa sintetis berprefix NISN `9999`. Jangan memakai kamera, foto, atau akun siswa nyata tanpa persetujuan eksplisit.

## Persiapan

1. Pastikan `FEATURE_TRUST_ENGINE_ENFORCE=true` hanya pada instance lomba dan migrasi competition sudah dijalankan.
2. Buat akun sintetis memakai seeder khusus lomba. Login sebagai siswa sintetis yang memiliki descriptor wajah.
3. Gunakan browser terbaru, HTTPS, izin kamera dan lokasi, serta kamera langsung. Buka halaman Presensi Face.
4. Pastikan respons halaman berisi script `/competition/liveness.js` dan header `Cache-Control: private, no-store, max-age=0`.

## Skenario

1. **Wajah langsung:** mulai presensi, ikuti instruksi acak di banner (kedip, tengok kiri layar, atau tengok kanan layar) sebelum challenge 90 detik kedaluwarsa. Sinyal memenuhi ambang harus meneruskan request; jika hasil masuk rentang review, presensi berstatus menunggu dan muncul di antrean admin.
2. **Challenge gagal:** tetap diam atau lakukan gerakan yang tidak sesuai instruksi. Server semestinya menolak sebelum controller dan menampilkan bahwa liveness tidak lolos.
3. **Foto cetak / foto pada layar:** gunakan hanya foto relawan yang telah memberi persetujuan. Hasil yang diharapkan adalah challenge gagal dan request ditolak. Jika salah satu foto melewati challenge, catat sebagai kegagalan uji; jangan mengklaim anti-spoofing. Satu percobaan manual tidak memberi jaminan keamanan.
4. **Kamera/model tidak tersedia:** setelah challenge berhasil diterbitkan, simulasi kegagalan kamera/model. Sistem dapat masuk review hanya bila skor wajah dan GPS memenuhi minimum serta batas harian siswa belum tercapai; sisanya ditolak dengan alasan yang dapat dibaca.
5. **Challenge tidak tersedia:** blokir endpoint challenge lalu kirim presensi. Server semestinya menolak request tanpa nonce valid sebelum controller.
6. **Antrean admin:** login dengan akun admin, buka `/competition/reviews`, tandai satu item Setuju dan item lain Mencurigakan. Status antrean dan audit event berubah; tabel `presensi` tidak berubah.

Catat browser/perangkat, jenis challenge, hasil, dan pesan yang terlihat. Jangan menyimpan atau membagikan foto/descriptor uji. Hasil pengujian kecil harus dilaporkan sebagai observasi, bukan sebagai klaim bahwa foto cetak, layar, deepfake, atau injeksi kamera selalu terdeteksi.
