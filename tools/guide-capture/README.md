# guide-capture

Membuat **screenshot per langkah** dan **gambar bergerak (GIF animasi)** untuk panduan, otomatis, dengan Playwright. Hasil ditulis ke `public/images/guide/` dan dipakai oleh:

- halaman Panduan di aplikasi (`/admin/panduan`, komponen `<x-guide.demo>`)
- `docs/cara-kerja-custom-attribute-pengingat-dokumen.md`

## Pakai

```bash
cd tools/guide-capture
npm install
npx playwright install chromium
npm run setup-db     # sekali: buat DB db_shelf_capture (struktur dari DB utama + seed minimum)
npm run capture      # semua bagian, diawali reset data contoh
node capture.mjs setup                                   # satu bagian
node capture.mjs --reset pengajuan-buat,pengajuan-proses # beberapa bagian + reset data dulu
```

Skrip menyalakan `php artisan serve` sementara di port 8099 dengan `DB_DATABASE=db_shelf_capture`, lalu menjalankan tiap bagian di `sections.mjs`. DB utama tidak disentuh; `lib.mjs` menolak nama DB yang tidak mengandung `capture`.

Akun contoh dari `GuideCaptureSeeder` (semua kata sandi `password`). Bagian memilih akun lewat `user: '...'`, default `admin`:

| Akun | Role | Dipakai di |
|---|---|---|
| `admin` | super_admin | aset, BA, servis, OB (menugaskan), kendaraan, pengguna, audit, asuransi, sewa, Notifikasi WA |
| `pemohon` | requester | membuat pengajuan dan laporan gangguan, konfirmasi |
| `accounting` | accounting | status PO |
| `executor` | executor | setujui jumlah, proses pengajuan dan gangguan |
| `ob` | office_boy | foto sebelum dan sesudah |
| `ga`, `budi` | general_affair, requester | pihak BA (serah terima, pengalihan, pengembalian) |

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
- Bagian `audit-kendaraan` membuat file Excel contoh lewat `php artisan tinker` (ekspor format audit kendaraan + satu mobil baru), jadi tidak ada file .xlsx di repo.
- Widget dasbor dan tabel relasi dimuat malas (lazy): gulir sampai elemennya terlihat sebelum screenshot.
- Navigasi Filament memakai `wire:navigate`, jadi tunggu URL dengan `waitUntil: 'commit'` (helper `waitPage` di `sections.mjs`), bukan event `load`.
- Cache permission Spatie dipakai bersama DB utama; skrip mengosongkannya sebelum dan sesudah capture.
- Jika langkah gagal, screenshot kegagalan disimpan di `fail.png` (di-ignore git).
- `migrate:fresh` pada DB kosong gagal karena dua migrasi tabel `settings`, itu sebabnya `setup-db` menyalin struktur dari DB utama.
