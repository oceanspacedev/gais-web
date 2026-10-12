# Data legacy sumo-web (Web Gais)

Modul Pengajuan Barang, Lapor Gangguan, Asuransi, Perjanjian Sewa, master barang, QR dan
notifikasi WA berasal dari aplikasi sumo-web. Data lamanya dibaca lewat koneksi database
`sumo` (lihat `config/database.php`, env `SUMO_DB_*`) oleh perintah:

```bash
php artisan sumo:import --dry-run   # laporan jumlah dan pemetaan, tanpa menulis
php artisan sumo:import             # impor sungguhan, bisa diulang (idempoten)
```

## Aturan utama: dump sumo TIDAK BOLEH di-import ke database shelf

Dump sumo memakai `DROP TABLE IF EXISTS` dan punya tabel bernama sama dengan shelf
(`users`, `roles`, `categories`, `divisions`, `migrations`, `personal_access_tokens`,
`failed_jobs`, `password_resets`). Di-import ke `db_shelf` berarti tabel shelf tertimpa,
login dan permission rusak, dan `php artisan migrate` berhenti bekerja.

Cara yang benar di server:

```bash
mysql -e "CREATE DATABASE db_sumo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql db_sumo < dump-sumo.sql
```

lalu di `.env` shelf:

```dotenv
SUMO_DB_DATABASE=db_sumo
```

(host, port, user, password mengikuti `DB_*` kecuali diisi `SUMO_DB_HOST` dst.)

## Catatan kejadian 12 Okt 2026 (lokal) dan cara memulihkannya

Dump sumo sempat di-import ke `db_shelf`. Pemulihan yang dilakukan, urutannya penting:

1. `mysqldump --single-transaction db_shelf > backup-penuh.sql` (simpan di luar repo).
2. Salin 30 tabel sumo ke database baru `db_sumo_latest`
   (`mysqldump db_shelf <daftar tabel sumo> | mysql db_sumo_latest`).
3. Muat backup shelf terakhir ke database sementara `db_shelf_bak`, lalu
   `mysqldump db_shelf_bak users roles categories divisions migrations personal_access_tokens failed_jobs > restore7.sql`
   dan `mysql db_shelf < restore7.sql`. mysqldump sudah mematikan `FOREIGN_KEY_CHECKS`,
   dan karena nama tabel sama, FK dari tabel shelf lain (asset_transfers, model_has_roles, dst)
   otomatis tersambung lagi. **Jangan** memakai `RENAME TABLE` pada tabel yang bentrok nama,
   karena FK tabel lain ikut pindah ke nama baru.
4. `SET FOREIGN_KEY_CHECKS=0; DROP TABLE <23 tabel sumo yang tidak bentrok nama>;`
5. Cek: `SELECT COUNT(*) FROM model_has_roles WHERE model_id NOT IN (SELECT id FROM users)` = 0,
   FK `asset_transfers` menunjuk `users`, `migrate:status` masuk akal.
6. Migration yang dibuat setelah tanggal backup tetapi efeknya sudah ada di DB dicatat manual
   ke tabel `migrations` (batch baru), lalu `php artisan migrate --pretend` harus hanya
   menampilkan migration yang efeknya memang belum ada, baru `php artisan migrate`.
7. `php artisan permission:cache-reset && php artisan optimize:clear`, jalankan
   `php artisan test --filter=NavigationGroupsTest`.

Yang hilang karena restore: perubahan pada 7 tabel itu antara tanggal backup dan kejadian.

## Setelah impor: langkah manual di server

1. **File unggahan sumo.** Salin folder berikut dari `storage/app/public` server sumo ke disk
   `public` shelf (lokal: `storage/app/public`, atau bucket S3 bila `FILESYSTEM_DISK=s3`),
   dengan nama folder yang sama: `Request_File`, `Problem_Report_File`, `Rent_File`, `product`.
   Nama file di database hanya nama filenya; shelf menambahkan folder saat menampilkan
   (`App\Support\LegacyUpload`).
2. **Scheduler.** Pastikan cron `php artisan schedule:run` tiap menit aktif. Jadwal baru:
   `problem-reports:auto-confirm` (tiap 10 menit) dan `reminder:whatsapp` (tiap jam, mengirim
   sekali sehari setelah jam kirim di menu Notifikasi WA).
3. **WhatsApp.** `WAG_URL` dan `WAG_TOKEN` sudah dipakai modul aset; nomor tujuan global diatur
   di menu Pengaturan > Notifikasi WA. Uji dengan tombol "Kirim Uji Coba".
4. **Akun sumo.** 87 akun aktif dibuat sebagai user shelf dengan password lamanya. Role: ADMIN ->
   `pengajuan_admin`, ACCOUNTING -> `accounting`, EXECUTOR -> `executor`, USER -> `requester`.
   Izin tambahan per divisi (HCM, AUDIT, MIS-IT) dan cakupan pengajuan per divisi ada di
   `config/sumo-import.php`; ubah lewat menu Users / Divisi bila perlu.
5. **Pemetaan badan usaha.** Periksa `config/sumo-import.php` bagian `business_entities`
   (mis. CV.CS dan PT. RISM punya kandidat lebih dari satu) sebelum impor di server; jalankan
   `php artisan sumo:import --dry-run` dulu dan baca peringatannya.
