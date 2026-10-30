# Data legacy sumo-web (Web Gais lama)

Modul Pengajuan Barang, Lapor Gangguan, Asuransi, Perjanjian Sewa, master barang, QR dan
notifikasi WA berasal dari aplikasi sumo-web. Dump database lamanya dimuat ke tabel staging
berawalan `sumo_dump_` di database GAIS (koneksi `sumo` di `config/database.php`, env
`SUMO_DB_*`), lalu dipetakan ke tabel GAIS oleh perintah:

```bash
php artisan sumo:import --file=sumo.sql.gz --dry-run   # muat dump ke staging, laporan tanpa menulis
php artisan sumo:import                                # impor sungguhan dari staging, bisa diulang (idempoten)
```

### Lewat panel: Pengaturan > Impor Data Sumo

Cara yang disarankan, karena setiap langkah tercatat (file, tabel yang dimuat, laporan, log):

1. **Unggah dump** hasil `mysqldump db_sumo | gzip > sumo.sql.gz` (.sql atau .sql.gz).
   File disimpan privat di disk `SUMO_IMPORT_DISK` (default `FILESYSTEM_DISK`).
2. Job antrean `long-running` (supervisor Horizon `supervisor-long`) **menghapus tabel staging
   lama** lalu menjalankan dump ke tabel berawalan `sumo_dump_` (`SUMO_DB_PREFIX`) di database
   GAIS, kemudian **uji coba** otomatis. Setiap nama tabel di dump diberi awalan, jadi
   `DROP TABLE users` di dump mengenai `sumo_dump_users`, bukan tabel GAIS. Tidak perlu membuat
   database atau memberi izin MySQL tambahan. Foreign key, view, trigger, dan routine di dump
   dilewati; pernyataan yang keluar dari tabel staging (`USE`, `db.tabel`, `UPDATE`,
   `RENAME TABLE`, `INSERT ... SELECT`, `GRANT`, `DROP DATABASE`, ...) ditolak.
3. Periksa **Hasil uji coba**: jumlah per langkah, pemetaan badan usaha, peringatan penggabungan user.
   Ubah `config/sumo-import.php` bila perlu, lalu **Uji coba ulang**.
4. **Impor sungguhan** (ketik `IMPOR`) memakai dump yang sama dengan uji coba terakhir, dalam satu
   transaksi. Backup database GAIS dulu.

Izin: `Manage:SumoImport` (awalnya hanya `super_admin`). Batas unggah default 12 MB
(`SUMO_IMPORT_MAX_UPLOAD_KB`); `upload_max_filesize`/`post_max_size` PHP dan `client_max_body_size`
nginx juga berlaku, jadi pakai .sql.gz. Setelah deploy, restart Horizon (`php artisan horizon:terminate`)
supaya supervisor baru jalan. Dump yang lebih besar tetap bisa lewat CLI di bawah.

### Lewat CLI

Impor tidak menggandakan data yang sudah ada di GAIS:

- **Badan usaha** tidak pernah dibuat baru. Semuanya dipetakan ke badan usaha GAIS lewat
  `business_entities` di `config/sumo-import.php`. Hasil pemetaannya tercetak di laporan
  (bagian "Pemetaan"), jadi periksa saat `--dry-run`.
- **User** digabung ke akun GAIS milik orang yang sama. Urutannya: `user_links` di config,
  lalu username yang sama, lalu nama lengkap (minimal dua kata) yang hanya dipakai satu user
  di GAIS dan satu user aktif di sumo. Nama, badan usaha, dan login GAIS dipertahankan.
  Akun GAIS yang belum punya username/password (data karyawan) dilengkapi login sumo.
  Nama yang dipakai lebih dari satu orang tidak ditebak; impor menulis peringatan, dan
  penggabungannya bisa diisi manual di `user_links`.
- **Akun sumo yang sudah dihapus** hanya dibuat (nonaktif, tanpa role) bila punya riwayat
  pengajuan, approval, laporan, atau kontrak.
- **Akses semua badan usaha** tidak diberikan otomatis ke akun GAIS yang sudah memakai role
  non-sumo, karena akses itu berlaku di seluruh GAIS. Impor menulis peringatan untuk akun ini.

Di server, install dengan `composer install --no-dev`. Dengan paket dev, Ignition merekam
setiap query beserta binding upsert 500 baris. Akibatnya impor bisa melewati `memory_limit`
128M begitu data sumo sekitar 5x ukuran sekarang. Kalau terpaksa menjalankan di lingkungan
dev, pakai `php -d memory_limit=512M artisan sumo:import`.

## Aturan utama: dump sumo TIDAK BOLEH dijalankan langsung ke database GAIS

Dump sumo memakai `DROP TABLE IF EXISTS` dan punya tabel bernama sama dengan GAIS
(`users`, `roles`, `categories`, `divisions`, `migrations`, `personal_access_tokens`,
`failed_jobs`, `password_resets`). `mysql db_gais < dump-sumo.sql` berarti tabel GAIS tertimpa,
login dan permission rusak, dan `php artisan migrate` berhenti bekerja.

Muat dump hanya lewat panel atau `php artisan sumo:import --file=...`: loader memberi awalan
`sumo_dump_` pada setiap tabel, dan menolak berjalan di database GAIS bila `SUMO_DB_PREFIX`
kosong atau awalannya juga dipakai tabel GAIS (mis. `sumo_` mengenai `sumo_import_runs`).

Mode lama, database staging tersendiri (opsional):

```bash
mysql -e "CREATE DATABASE db_sumo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON db_sumo.* TO '<DB_USERNAME>'@'localhost'"
```

lalu di `.env` GAIS (host, port, user, password mengikuti `DB_*` kecuali diisi `SUMO_DB_HOST` dst.):

```dotenv
SUMO_DB_DATABASE=db_sumo
SUMO_DB_PREFIX=
```

## Catatan kejadian 12 Okt 2026 (lokal) dan cara memulihkannya

Dump sumo sempat di-import ke `db_gais`. Pemulihan yang dilakukan, urutannya penting:

1. `mysqldump --single-transaction db_gais > backup-penuh.sql` (simpan di luar repo).
2. Salin 30 tabel sumo ke database baru `db_sumo_latest`
   (`mysqldump db_gais <daftar tabel sumo> | mysql db_sumo_latest`).
3. Muat backup GAIS terakhir ke database sementara `db_gais_bak`, lalu
   `mysqldump db_gais_bak users roles categories divisions migrations personal_access_tokens failed_jobs > restore7.sql`
   dan `mysql db_gais < restore7.sql`. mysqldump sudah mematikan `FOREIGN_KEY_CHECKS`,
   dan karena nama tabel sama, FK dari tabel GAIS lain (asset_transfers, model_has_roles, dst)
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
   `public` GAIS (lokal: `storage/app/public`, atau bucket S3 bila `FILESYSTEM_DISK=s3`),
   dengan nama folder yang sama: `Request_File`, `Problem_Report_File`, `Rent_File`, `product`.
   Nama file di database hanya nama filenya; GAIS menambahkan folder saat menampilkan
   (`App\Support\LegacyUpload`).
2. **Scheduler.** Pastikan cron `php artisan schedule:run` tiap menit aktif. Jadwal baru:
   `problem-reports:auto-confirm` (tiap 10 menit) dan `reminder:whatsapp` (tiap jam, mengirim
   sekali sehari setelah jam kirim di menu Notifikasi WA).
3. **WhatsApp.** `WAG_URL` dan `WAG_TOKEN` sudah dipakai modul aset; nomor tujuan global diatur
   di menu Pengaturan > Notifikasi WA. Uji dengan tombol "Kirim Uji Coba".
4. **Akun sumo.** 87 akun aktif dibuat sebagai user GAIS dengan password lamanya. Role: ADMIN ->
   `pengajuan_admin`, ACCOUNTING -> `accounting`, EXECUTOR -> `executor`, USER -> `requester`.
   Izin tambahan per divisi (HCM, AUDIT, MIS-IT) dan cakupan pengajuan per divisi ada di
   `config/sumo-import.php`; ubah lewat menu Users / Divisi bila perlu.
5. **Pemetaan badan usaha.** Periksa `config/sumo-import.php` bagian `business_entities`
   (mis. CV.CS dan PT. RISM punya kandidat lebih dari satu) sebelum impor di server; jalankan
   `php artisan sumo:import --dry-run` dulu dan baca peringatannya.
