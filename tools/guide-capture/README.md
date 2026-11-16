# guide-capture

Membuat **screenshot per langkah** dan **gambar bergerak (GIF animasi)** untuk panduan, otomatis, dengan Playwright. Hasil ditulis ke `public/images/guide/` dan dipakai oleh:

- halaman Panduan di aplikasi (`/admin/panduan`, komponen `<x-guide.demo>`)
- `docs/cara-kerja-custom-attribute-pengingat-dokumen.md`

## Pakai

```bash
cd tools/guide-capture
npm install
npx playwright install chromium
npm run setup-db     # sekali: buat DB db_gais_capture (struktur dari DB utama + seed minimum)
npm run capture      # semua bagian, diawali reset data contoh
node capture.mjs setup                                   # satu bagian
node capture.mjs --reset pengajuan-buat,pengajuan-proses # beberapa bagian + reset data dulu
```

Skrip menyalakan `php artisan serve` sementara di port 8099 dengan `DB_DATABASE=db_gais_capture`, lalu menjalankan tiap bagian di `sections.mjs`. DB utama tidak disentuh; `lib.mjs` menolak nama DB yang tidak mengandung `capture`.

Akun contoh dari `GuideCaptureSeeder` (semua kata sandi `password`). Bagian memilih akun lewat `user: '...'`, default `admin`:

| Akun | Role | Dipakai di |
|---|---|---|
| `admin` | super_admin | aset, BA, servis, OB (menugaskan), pertanyaan Security Checksheet, kendaraan, pengguna, audit kendaraan dan CSA, asuransi, sewa, Notifikasi WA, Pekerjaan Vendor |
| `pemohon` | requester | membuat pengajuan dan laporan gangguan, konfirmasi |
| `accounting` | accounting | status PO |
| `executor` | executor | setujui jumlah, proses pengajuan dan gangguan |
| `ob` | office_boy | foto sebelum dan sesudah |
| `ga`, `budi` | general_affair, requester | pihak BA (serah terima, pengalihan, pengembalian) |
| `satpam` | security | mengisi Security Checksheet |

Reset (`resetData`) menghapus pengajuan, laporan, BA, servis, checksheet, dan data buatan bagian `setup`/`isi-aset`/`user-baru`, lalu menjalankan ulang seeder sehingga aset stok kembali Tersedia.

Override lewat env: `CAPTURE_DB`, `PHP_BIN` (PHP 8.4), `MYSQL_BIN` (folder `mysql.exe`/`mysqldump.exe`).

## Menambah bagian baru

Tambah objek di `sections.mjs`:

```js
{
  id: 'nama-bagian',            // jadi nama-bagian.gif dan nama-bagian-01-*.png
  async run(page, r) {
    await page.goto(`${BASE}/admin/...`);
    await r.fill('Nama', 'contoh');          // ketik per huruf, kursor merah bergerak
    await r.select('Kategori', 'MOBIL');     // select Filament (native maupun searchable)
    await r.date('Tanggal Pembelian', '2026-10-13');
    await r.click(page.locator('button[type=submit]:visible').first());
    await r.shot('hasil', page.locator('.fi-section').first()); // elemen kedua = diberi bingkai merah
    // Lainnya: r.action('Label tombol header') membuka modal, r.radio('Selesai'), r.submitModal(),
    // r.upload('Label', file), r.camera('Label', file) untuk komponen kamera, r.sampleImage(nama, html).
  },
}
```

Lalu tampilkan di panduan: `<x-guide.demo name="nama-bagian" caption="..." :shots="['nama-bagian-01-hasil.png' => 'Hasil']" />`.

## Catatan

- Bagian yang saling bergantung harus dijalankan berurutan dalam satu perintah: `setup` → `isi-aset` → `aset-daftar`, dan `pengajuan-buat` → `-accounting` → `-proses` → `-konfirmasi` (begitu juga `gangguan-*`, `ob-*`, dan `ba-serah-terima` → `ba-pengalihan` → `ba-pengembalian`). Kuota pengajuan (2 terbuka per tipe) dan batas satu laporan gangguan terbuka membuat pengulangan tanpa `--reset` bisa gagal.
- Bagian `audit-kendaraan` dan `audit-csa` membuat file Excel contoh lewat `php artisan tinker` (ekspor format audit + baris temuan auditor), jadi tidak ada file .xlsx di repo. Stok contoh CSA (KERTAS THERMAL 80MM, qty 10 di GUDANG PUSAT) dan vendor `CV Sumber Teknik` untuk bagian `vendor-tugas` dibuat oleh seeder.
- Widget dasbor dan tabel relasi dimuat malas (lazy): gulir sampai elemennya terlihat sebelum screenshot.
- Navigasi Filament memakai `wire:navigate`, jadi tunggu URL dengan `waitUntil: 'commit'` (helper `waitPage` di `sections.mjs`), bukan event `load`.
- Cache permission Spatie dipakai bersama DB utama; skrip mengosongkannya sebelum dan sesudah capture.
- Jika langkah gagal, screenshot kegagalan disimpan di `fail.png` (di-ignore git).
- Di macOS: `setup-db` belum bisa dipakai (memanggil `mysql.exe`/`mysqldump.exe`), jadi perbarui DB capture dengan `DB_DATABASE=db_gais_capture php artisan migrate --force` lalu `permission:cache-reset`; jalankan capture dengan `PHP_BIN="$(which php)" node capture.mjs ...` karena default `PHP_BIN` adalah path Laragon Windows.
- `migrate:fresh` pada DB kosong gagal karena dua migrasi tabel `settings`, itu sebabnya `setup-db` menyalin struktur dari DB utama.

## Video demo (MP4)

`demo-video.mjs` merekam video demo utuh dari sudut pandang **pemohon/karyawan**: login lewat **WhatsApp OTP** dan **Email**, Pengajuan Barang → Konfirmasi Penerimaan, Lapor Gangguan → Konfirmasi Selesai. Ada kartu judul, teks keterangan, dan **musik latar instrumental orisinal** dari `music.mjs` (dibuat dari nol, bebas hak cipta). Langkah executor dikerjakan di luar kamera. Hasil: `video-out/demo-pemohon.mp4` (di-ignore git).

- Login WA/Email memakai server tiruan lokal (WA di port 8097, SMTP di port 2526) yang menangkap kode OTP, jadi tidak ada pesan yang benar-benar terkirim. Nomor login akun `pemohon` diisi oleh `GuideCaptureSeeder`.
- `DEMO=ga` membuat video sisi **Tim GA** (akun `executor`): Dasbor Perlu Tindakan, Proses pengajuan (Diproses → Selesai), Lapor Gangguan (Jadwal Pengerjaan → Selesai + Foto Hasil), pemantauan setelah pemohon konfirmasi. Hasil: `video-out/demo-ga.mp4`.
- `MUSIC_FILE=lagu.mp3` untuk lagu sendiri (diulang kalau kurang panjang), `MUSIC_VOLUME` (default 0.6).
- `NARRATION=1` mengganti musik dengan narasi suara `say -v Damayanti` (opsional, kaku).

```bash
CAPTURE_PORT=8098 PHP_BIN="$(which php)" node demo-video.mjs
```

`CAPTURE_PORT` dipakai kalau port 8099 sedang dipakai server lain (mis. preview `gais-test`). Foto unggahan butuh `php artisan storage:link`.
