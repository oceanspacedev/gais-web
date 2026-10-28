# Deploy rebranding Shelf → GAIS

Langkah sekali jalan saat merilis perubahan nama aplikasi menjadi **GAIS (General Affair Information System)** ke production.

## Yang ikut berubah

- `APP_NAME` menjadi `GAIS`. Nama cookie sesi, prefix cache, prefix Redis, dan prefix Horizon diturunkan dari `APP_NAME`, jadi semuanya ikut berganti (`gais_session`, `gais_cache_`, `gais_database_`, `gais_horizon:`).
- Kolom `asset_reconciliation_items.shelf_qty` menjadi `gais_qty`, kunci summary `orphan_shelf_plates` menjadi `orphan_gais_plates`, dan setting `site_name`, `pwa_app_name`, `pwa_short_name` yang masih bernilai `Shelf` menjadi `GAIS` (migration `2026_10_28_200000_rebrand_shelf_to_gais`).
- Kontrak integrasi WhatsApp: route query aset sekarang `gais.search_asset`, `gais.asset_history`, `gais.expiring_documents`. Route `shelf.*` ditolak dengan 422. Pengiriman ke gateway memakai `client_reference: gais` dan `Idempotency-Key` berawalan `gais-`.
- Export audit kendaraan menulis kolom `GAIS` di posisi kolom `SHELF`. Import tetap menerima workbook lama yang berkolom `SHELF`.
- Contoh unit worker berganti nama menjadi `deploy/web-gais-queue.service`.

## Yang tidak berubah

Domain (`shelf.completeselular.com`), bucket S3 `shelf`, database `db_shelf`, dan folder server `/var/www/web-shelf`. Repo GitHub belakangan di-rename menjadi `oceanspacedev/gais-web`; GitHub mengalihkan URL lama, tetapi perbarui `git remote set-url origin` di server dan mesin developer. Ikon panel dan favicon panel (`public/images/icon.svg`) sudah memakai logo GAIS, tetapi ikon PWA (PNG di `public/images/icons/` dan yang diunggah di Pengaturan PWA) serta `public/favicon.ico` masih logo lama.

## Urutan deploy

1. Koordinasikan dengan pengelola bot WhatsApp: bot harus memakai route `gais.*` tepat setelah deploy ini.
2. Kosongkan antrean lama. Job yang masih menunggu di prefix Redis `shelf_` tidak akan diambil worker setelah prefix berganti.
   ```bash
   php artisan horizon:pause
   ```
   Tunggu sampai **Jobs Pending** di `/horizon` bernilai 0 untuk semua queue (`notifications`, `default`, `exports`, `long-running`), lalu:
   ```bash
   php artisan horizon:terminate
   ```
3. Ubah `.env` production: `APP_NAME=GAIS`. `MAIL_FROM_NAME="${APP_NAME}"` ikut otomatis.
4. Tarik kode dan jalankan:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan optimize
   php artisan filament:optimize
   ```
5. Nyalakan worker lagi. Bila ingin ikut memakai nama unit baru:
   ```bash
   sudo cp deploy/web-gais-queue.service /etc/systemd/system/
   sudo systemctl disable --now web-shelf-queue
   sudo systemctl daemon-reload
   sudo systemctl enable --now web-gais-queue
   ```
   Bila unit lama tetap dipakai, cukup `sudo systemctl restart web-shelf-queue`.

## Dampak ke pengguna

- Semua pengguna keluar sekali dan harus login ulang.
- Kode OTP WhatsApp/email yang sedang berjalan tidak berlaku; minta kode baru.
- Riwayat job di dashboard Horizon mulai kosong. Job gagal tetap tercatat di tabel `failed_jobs`.
- Aplikasi PWA yang sudah terpasang menampilkan nama baru setelah browser memuat ulang manifest.
