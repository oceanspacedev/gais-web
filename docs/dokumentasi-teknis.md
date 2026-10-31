# Dokumentasi Teknis GAIS

Halaman **Dokumentasi Teknis** di panel (`/admin/dokumentasi-teknis`) merender file ini. Setiap `##` menjadi satu bab, setiap `###` menjadi bagian yang bisa dibuka-tutup, dan komentar `<!-- desc: ... -->` di bawah judul bab menjadi deskripsi singkatnya. Ditulis 14 Oktober 2026, diperbarui 27 Oktober 2026.

## Ringkasan
<!-- desc: Apa itu GAIS dan dari mana modulnya berasal -->

GAIS (General Affair Information System) adalah aplikasi manajemen aset internal. Dibangun dengan Laravel 12 + Filament 4, dipakai staf lewat satu panel di `/admin`.

### Apa yang dikerjakan aplikasi

- Inventaris aset beserta kondisi dan pemegangnya.
- Transfer aset dan berita acara (BA, BAPAB, BAPEB).
- Rekonsiliasi audit (CSA dan audit kendaraan).
- Checksheet kendaraan dan OB.
- Pengingat masa berlaku dokumen lewat WhatsApp.
- Modul pindahan sumo-web: Pengajuan Barang, Lapor Gangguan, Asuransi & Sewa.

### Asal modul sumo-web

Modul dari aplikasi lama **sumo-web (Web Gais lama)** dibangun ulang sebagai resource Filament dan sudah di-merge ke `main`. Data lamanya diimpor lewat menu **Pengaturan > Impor Data Sumo** atau command `sumo:import`.

### Hubungan dengan README

- `README.md`: cara menjalankan aplikasi dan aturan operasional.
- Dokumen ini: peta kode, relasi database, dan cara mengembangkan modul.

### Fakta dasar

| Hal | Nilai |
| --- | --- |
| Bahasa UI | Indonesia (locale `id`) |
| Zona waktu | `Asia/Jakarta`, di-hardcode di `config/app.php` |
| Branch utama | `main` |
| Pola branch kerja | `feat/<scope>`, `fix/<scope>`, `docs/<scope>` |

## Tech stack
<!-- desc: Framework, library, dan versinya -->

Monolit Laravel 12 dengan panel Filament 4 (Livewire 3). Tidak ada SPA dan tidak ada REST API domain terpisah.

### Inti aplikasi

| Lapisan | Teknologi | Versi |
| --- | --- | --- |
| Bahasa | PHP | **8.4.1+** (vendor), meski composer.json menulis ^8.2 |
| Framework | Laravel | 12.69.2 |
| Admin UI | Filament | 4.11.8 |
| Reaktivitas | Livewire | 3.8.2 |
| Tema | kungfufafa/mekaya-theme | dev-main |
| Database | MySQL | 8.0 |
| Frontend build | Vite + Tailwind CSS | 8.1 / 4.3 |

### Library pendukung

| Kebutuhan | Paket | Versi |
| --- | --- | --- |
| Role & permission | Filament Shield, Spatie Permission | 4.2.0 / 7.4.2 |
| Queue | Laravel Horizon + Redis | 5.49.0 |
| Monitoring | Laravel Pulse, log-viewer, filament-exceptions | 1.7.4 / 3.x / 4.x |
| PDF | barryvdh/laravel-dompdf | 3.1.2 |
| Excel | filament-excel, filament-excel-import, PhpSpreadsheet | 3.6.1 / 4.0.0 / 1.30.5 |
| QR code | endroid/qr-code | 5.1.0 |
| Storage S3 | league/flysystem-aws-s3-v3 | 3.x |
| Test & format | PHPUnit, Laravel Pint | 11.5.56 / 1.29.3 |

Plugin panel lain: filament-pwa, filament-settings-hub, filament-language-switch, filament-impersonate, overlook, resized-column.

### Layanan eksternal

- **WagHub** (`WAG_URL`, `WAG_TOKEN`): notifikasi, pengingat, dan OTP login WhatsApp.
- **SMTP** (opsional): email notifikasi dan login email (kode + magic link).
- **S3 / MinIO** (opsional): lampiran di production.

Versi diambil dari `composer.lock` dan `package.json` per 14 Oktober 2026.

## Setup lokal
<!-- desc: Instalasi dan menjalankan aplikasi di Laragon -->

Butuh PHP **8.4+**, MySQL 8, Redis, dan Node. Contoh di bawah memakai `php`; di Laragon (Windows) `php` di PATH bisa masih 8.3, jadi buat alias ke binary 8.4 seperti contoh.

### Instalasi

```bash
# Laragon (Git Bash) saja, bila php di PATH masih 8.3:
# alias php=/c/laragon/bin/php/php-8.4.25-Win32-vs17-x64/php.exe

composer install
cp .env.example .env
php artisan key:generate
npm ci

# .env: DB_HOST, DB_PORT, DB_DATABASE sesuai MySQL lokal (Laragon: port 3308, db_gais)
php artisan migrate --seed
php artisan storage:link
```

### Menjalankan aplikasi

Jalankan masing-masing di terminal terpisah:

1. `php artisan serve` (atau virtual host Laragon/Herd), lalu buka `/admin`.
2. `npm run dev` untuk Vite, atau `npm run build` sekali saja.
3. `php artisan horizon` bila menyentuh notifikasi, export, atau Impor Data Sumo. Butuh Redis.
4. `php artisan schedule:work` bila mengembangkan pengingat atau auto-confirm.

### Database legacy sumo

Hanya dibutuhkan untuk import. Koneksinya `sumo` di `config/database.php`. Bawaannya **staging** adalah tabel berawalan `sumo_dump_` (`SUMO_DB_PREFIX`) di database GAIS: impor lewat panel atau `sumo:import --file` menghapus tabel itu lalu memuat dump yang diunggah dengan nama tabel diberi awalan. Database staging tersendiri tetap bisa dipakai lewat `SUMO_DB_DATABASE` dengan `SUMO_DB_PREFIX` kosong.

### Peringatan

- Feature test berjalan di database `.env` dengan `DatabaseTransactions`. **Jangan** jalankan `migrate:fresh`, `migrate:refresh`, atau `db:wipe` di sana.
- **Jangan** impor dump sumo langsung ke database GAIS: 8 tabel bernama sama akan tertimpa (insiden 12 Oktober 2026). Muat dump hanya lewat panel atau `sumo:import --file`, yang memberi awalan `sumo_dump_` pada setiap tabel; loader menolak database GAIS tanpa awalan. Langkahnya di `database/sumo/README.md`.

## Struktur kode
<!-- desc: Peta folder, rute, dan jadwal command -->

Pola Laravel standar: panel Filament → model Eloquent, logika bisnis di `app/Services`, akses dijaga policy + Shield. Tidak ada lapisan repository.

### Alur sebuah request

1. User membuka menu di panel `/admin` (resource atau page Filament).
2. Policy + permission Shield memutuskan boleh atau tidak.
3. Resource membaca/menulis model, dibatasi scope badan usaha.
4. Aturan multi-langkah dijalankan service di `app/Services`.
5. Pekerjaan berat (notifikasi, export) dikirim ke queue Horizon.

### Folder di app/Filament

| Folder | Isi |
| --- | --- |
| `Resources` | 36 resource CRUD. Satu resource = `XxxResource.php` + `Pages/`, opsional `RelationManagers/` dan `Widgets/` |
| `Pages` | Halaman custom: `Guide`, `TechnicalDocs`, `ScanItemRequestQr`, `ScanProductQr`, `WaNotificationSettings`, `Auth/Login` |
| `Auth/Pages` | `PhoneLogin` (login OTP WhatsApp di `/phone-login`), `EmailLogin` (kode + magic link di `/email-login`) |
| `Widgets` | 33 widget dasbor manajemen (statistik, grafik Chart.js, tabel) beserta trait `Concerns/GuardsWidgetWithPermission`; disusun per seksi modul oleh `Pages/Dashboard.php` |
| `Actions`, `Exports` | Export format audit, exporter aset, BA, servis, checksheet OB |

Konfigurasi panel (plugin, tema, grup navigasi) ada di `app/Providers/Filament/AdminPanelProvider.php`.

### Folder app lainnya

| Folder | Isi |
| --- | --- |
| `Models` | 53 model. `Concerns/HasBusinessEntityAccess` = scope akses per badan usaha; `HasVisibility` dari `app/Access` di setiap model fitur |
| `Enums` | Enum status. Trait `Concerns/Utilities` untuk label dan opsi |
| `Services` | Workflow, rekonsiliasi, QR, notifikasi, WhatsApp, ekspor rekap pengajuan (`ItemRequestRecapExport`, `ItemRequestMasterQrExport`), `TalentaUserImportService`, `Sumo/` (`SumoImporter`, `SumoDumpLoader`, `SqlDumpReader`) |
| `Support` | Helper kecil: normalizer, `StoredFile`, `LegacyUpload`, `RelationSearch` (pencarian relasi cepat), `IndexedPermissionRegistrar` (cek permission ter-indeks) |
| `Policies` | 39 policy hasil `shield:generate` |
| `Imports` | Import Excel aset, produk, asuransi, sewa, checksheet kendaraan, serta baris pembaharuan/perpanjangan per kontrak (`InsuranceUpdateImport`, `RentUpdateImport`). User **tidak** lewat Excel: hanya JSON Talenta (`TalentaUserImportService`) |
| `Exports` | Ekspor Maatwebsite berkolom template impor (sumo: export + template per modul), diunduh lewat `Filament/Actions/DownloadExcelAction`: `ProductExport`, `InsuranceExport`, `RentExport` (semua baris yang boleh dilihat), `InsuranceUpdateExport`, `RentUpdateExport` (baris pembaharuan satu kontrak), `UserExport` (kolom sumo, izin `export_user`), `ProblemReportExport` (rentang tanggal pelaporan, kolom sumo). Exporter antrean Filament ada di `Filament/Exports` |
| `Jobs`, `Mail` | Kirim notifikasi aset via WhatsApp dan email; `Jobs/Sumo` (`LoadSumoDump`, `RunSumoImport`) untuk Impor Data Sumo; `Jobs/Filament` menimpa job export/import Filament |
| `Queue` | `RestoresQueuedModels`: job export tidak gagal bila baris `exports` belum terlihat |
| `Console/Commands` | Pengingat, auto-confirm, import sumo, migrasi S3, generate QR |
| `Http/Controllers` | PDF, label QR, QR publik, upload kamera, unduhan bertanda tangan, integrasi WhatsApp |

### Folder di luar app

| Folder | Isi |
| --- | --- |
| `config/` | Konfigurasi domain: `item-request.php`, `asset-transfer.php`, `asset-reconciliation.php`, `sumo-import.php` |
| `database/migrations` | 117 migration |
| `database/sumo` | README import dan pemulihan data sumo |
| `tests/` | `Feature`, `Unit`, dan `Support/SumoLegacyFixture.php` |
| `docs/` | Dokumen ini dan dokumen pendukung lain |

File paling besar: `AssetResource.php` (±1.450 baris), lalu `ObChecksheetResource`, `AssetTransferResource`, `AssetServiceResource`, `ItemRequestResource`.

### Rute di luar panel

| File | Rute |
| --- | --- |
| `routes/web.php` | `/` → `/admin`, unduh PDF, cetak label QR, `qr/{qr}` publik, `phone-login`, `email-login` |
| `routes/api.php` | `/api/user` (Sanctum), query aset WhatsApp (tanpa auth) |

### Jadwal command

| Command | Jadwal | Didaftarkan di |
| --- | --- | --- |
| `notifications:send-scheduled` | Harian 13:20 | `bootstrap/app.php` |
| `model:prune` | Harian | `bootstrap/app.php` |
| `horizon:snapshot` | Tiap 5 menit | `routes/console.php` |
| `problem-reports:auto-confirm` | Tiap 10 menit | `routes/console.php` |
| `reminder:whatsapp` | Tiap jam | `routes/console.php` |

## Modul
<!-- desc: Menu panel dan file yang perlu dibuka per modul -->

Menu dikelompokkan dalam 10 grup, urutannya diatur di `AdminPanelProvider::navigationGroups()`. Buka grup di bawah untuk melihat file yang terlibat.

### Aset

- **Resource:** `AssetResource`, `AssetTransferResource` (BA), `AssetServiceResource`, `AssetReconciliationResource` (Import & Laporan Audit), `AssetQrLabelHistoryResource`
- **Pencarian:** kotak cari daftar aset (dan pemilih aset di form BA dan servis) juga mencocokkan semua atribut kustom aktif non-dokumen, misalnya plat nomor dan serial number. Spasi, strip, titik, dan garis miring diabaikan (`Asset::scopeWhereAnyAttribute` memakai `asset_attributes.search_value`)
- **Logika:** `AssetReconciliationService`, `VehicleAssetReconciliationService`, `*AuditWorkbookParser/Exporter`, `AssetQrService`, `AssetQrScanService`, `AssetNotificationService`
- **Asal:** GAIS

### Master Aset

- **Resource:** `CategoryResource`, `BrandResource`, `CustomAssetAttributeResource`, `VendorResource`
- **Logika:** command `notifications:send-scheduled` untuk atribut dokumen
- **Asal:** GAIS

### OB / Kebersihan

- **Resource:** `ObChecksheetResource`, `ObRoomResource`, `ObShiftResource`
- **Logika:** `ObTaskAssignmentService`
- **Asal:** GAIS

### Kendaraan

- **Resource:** `VehicleChecksheetResource`
- **Logika:** `VehicleChecksheetImport`, `VehiclePlateNormalizer`
- **Asal:** GAIS

### Pekerjaan Vendor

- **Resource:** `TaskResource`
- **Logika:** `PdfController` (PDF penyelesaian tugas)
- **Asal:** GAIS

### Pengajuan Barang

- **Resource:** `ItemRequestResource`, `ItemRequestLogResource`, `ItemRequestSettingResource`, `ItemRequestTypeResource`, `ProductResource`, `ProductCategoryResource`, `UnitTypeResource`
- **Page:** `ScanItemRequestQr`, `ScanProductQr`
- **Logika:** `ItemRequestWorkflow` (persetujuan atasan, accounting, executor), `ItemRequest::visibility()` (rule di `app/Access/ItemRequests`), `config/item-request.php`, `SumoQrLabelController`
- **Ekspor:** `ItemRequestRecapExport` (Rekap per Divisi: pivot barang x divisi per area, tipe, rentang `requested_at`, dan filter status `ItemRequestRecapStatus`; jumlah = `COALESCE(qty_approved, qty_request)`) dan `ItemRequestMasterQrExport` (`id` detail, `nama_barang`, `pemohon` untuk cetak QR massal). Keduanya aksi header `ListItemRequests` dengan izin `export_item::request`, ditulis lewat maatwebsite/excel, dan memakai `ItemRequestResource::scopeToViewer()` supaya angkanya sama dengan daftar.
- **Asal:** sumo-web. Setiap perubahan status dicatat di `item_request_logs`.

### Lapor Gangguan

- **Resource:** `ProblemReportResource`, `ProblemReportCategoryResource`
- **Logika:** `ProblemReportWorkflow` (dibuka, dikerjakan, dikonfirmasi otomatis bila pelapor diam), `ProblemReport::visibility()` (rule di `app/Access/ProblemReports`, cakupan jenis per divisi di `problem_report_scopes`), command `problem-reports:auto-confirm`
- **Asal:** sumo-web

### Asuransi & Sewa

- **Resource:** `InsuranceResource`, `InsuranceCategoryResource`, `InsuranceProviderResource`, `InsuranceScopeResource`, `RentResource`
- **Logika:** `ContractReminderService`, command `reminder:whatsapp`
- **Impor/ekspor pembaharuan:** header action tab Riwayat (`Filament/Actions/ContractUpdateExcelActions`) memakai `InsuranceUpdateImport`/`RentUpdateImport` dan `InsuranceUpdateExport`/`RentUpdateExport`. Kolom mengikuti template sumo (`no_polis_induk` / `kode_sewa_induk` wajib sama dengan kontrak yang dibuka); baris lama dicocokkan per (kontrak, `no_polis`/`kode`), sel kosong tidak mengubah baris lama, baris baru wajib bertanggal, status kosong = BERJALAN, `kode` kosong = `RentUpdate::nextCode()`. Kesalahan dilempar sebagai `ImportStoppedException` agar tampil sebagai notifikasi dan seluruh file dibatalkan. Izin: `import_*` dan `export_*` kontrak
- **Asal:** sumo-web

### Organisasi

- **Resource:** `UserResource`, `BusinessEntityResource`, `DivisionResource`, `JobTitleResource`, `AreaResource`, `AssetLocationResource`
- **Logika:** `TalentaUserImportService` (satu-satunya import user, dari JSON Talenta), `HasBusinessEntityAccess`
- **Halaman user:** lima tab relasi baca-saja di `UserResource/RelationManagers`: Aset Dipegang, Berita Acara (BA), Pengajuan Barang, Approval Pengajuan, Laporan Gangguan
- **Talenta:** user yang punya `employee_id` dianggap tersinkron dari Talenta (`UserResource::isSyncedFromTalenta`). Nama, Employee ID, WhatsApp, email, badan usaha, dan jabatan dikunci di form dan hanya berubah lewat import ulang JSON
- **Asal:** GAIS

### Pengaturan dan lainnya

- **Pengaturan:** page `WaNotificationSettings`, `SumoImportRunResource` (Impor Data Sumo), role Shield, `ExceptionResource`; logika di `WhatsappService`, `WhatsAppGateway`, dan `app/Services/Sumo`
- **Di luar grup:** page Panduan (`Guide.php`), page Dokumentasi Teknis (`TechnicalDocs.php`), dan dasbor manajemen (`Dashboard.php`: kartu Perlu Tindakan lalu seksi Aset, Pengajuan Barang, Lapor Gangguan, Asuransi & Sewa, Operasional, Organisasi & Data Master; widget dari `app/Filament/Widgets`, helper format/deret bulan/opsi grafik di `app/Support/Dashboard`)

## Database
<!-- desc: Peta tabel, kolom kunci, dan relasi Eloquent -->

MySQL `db_gais`, dibangun dari 117 migration. Dua tabel jadi poros: `users` dan `business_entities`. Nama tabel mengikuti konvensi Laravel (jamak, snake_case).

### Peta tabel

<!-- erd -->

Setiap domain terhubung ke inti lewat `user_id` dan/atau `business_entity_id`.

### Kolom kunci

| Tabel | Kolom yang perlu dipahami |
| --- | --- |
| `users` | `business_entity_id`, `access_all_business_entities`, `division_id`, `approver_user_id` (atasan), `whatsapp_number`, `whatsapp_login_number` (unik, OTP), `employee_id` (Talenta; terisi = field identitas dikunci di form), `legacy_sumo_user_id` |
| `email_otps` | `user_id`, `email` (huruf kecil), `otp_hash`, `token_hash` (sha256 magic link, unik), `expires_at`, `verified_at`, `attempt_count`; satu baris per permintaan login email, kode dan link sekali pakai bersama |
| `assets` | `condition_status` (Tersedia, Digunakan, Dijual, Hilang, Rusak), `recipient_id` (pemegang, kosong bila Tersedia), `recipient_business_entity_id`, `nbh_*`, `sold_*`, `qty`, `last_reconciliation_id`. Serial number, IMEI 1/2, dan tipe **bukan kolom**: lihat `custom_asset_attributes.key` |
| `custom_asset_attributes` | `key` (`serial_number`, `imei1`, `imei2`, `type`): atribut identitas aset yang dibaca kode lewat key, jadi namanya boleh diganti admin tetapi tidak bisa dihapus. Di kode tetap `$asset->serial_number` (properti virtual di `Asset`, simpan ke `asset_attributes`); query memakai `whereIdentity()` / `identitySubquery()`, dan daftar aset memuat `withIdentity()` supaya tidak N+1 |
| `asset_attributes` | `asset_id`, `custom_attribute_id`, `value`, `search_value` (nilai dinormalisasi tanpa spasi/strip/titik/garis miring, diisi otomatis saat simpan; dipakai pencarian daftar aset) |
| `sumo_import_runs` | `status`, `file_disk`/`file_path`, `staged`, `source_tables`, `dry_run_report`, `import_report`, `log`, `error`, `user_id`, `imported_by_id`; satu baris per unggahan di menu Impor Data Sumo |
| `asset_transfers` | `document_type` (BA, BAPAB, BAPEB), `letter_number`, `from_user_id`, `to_user_id`, `transfer_date`, `document` |
| `item_requests` | `request_code`, `item_request_type_id`, `total_cost`, `po_status`, `po_number`, `client_status`, `closed_by_user_id` |
| `problem_reports` | `code`, `status`, `client_status`, `scheduled_at`, `photo_before`, `photo_after`, `closed_by_user_id` |

### Relasi organisasi dan aset

| Model | Relasi |
| --- | --- |
| `BusinessEntity` | hasMany `User`, `Asset`, `AssetTransfer`; belongsToMany `User` via `business_entity_user` |
| `User` | belongsTo `BusinessEntity`, `JobTitle`, `Division`, `User` (approver) |
| `Area` / `Division` | `Area` hasMany `Division`; `Division` hasMany `User`, `DivisionApprover`, `ItemRequestScope`, `ProblemReportScope` |
| `Asset` | belongsTo `BusinessEntity`, `Category`, `Brand`, `AssetLocation`, `AssetCatalogItem`, `User` (`recipient_id`, `nbh_responsible_user_id`); hasMany `AssetAttribute`, `AssetService`, `AssetTransferDetail`, `VehicleChecksheet`; hasOne `AssetQr` |
| `AssetQr` | hasMany `AssetQrScan` |
| `Category` | hierarki lewat `parent_id`; hasMany `Asset` |
| `CustomAssetAttribute` | belongsTo `Category`; hasMany `AssetAttribute` (`custom_attribute_id`) |
| `AssetTransfer` | belongsTo `BusinessEntity`, `User` (from/to); hasMany `AssetTransferDetail` → `Asset` |
| `AssetService` | belongsTo `Asset`, `Vendor`, `User`; hasMany `AssetServiceItem` |
| `AssetReconciliation` | belongsTo `BusinessEntity`, `User` (`imported_by`), induk `parent_id`; hasMany `AssetReconciliationItem` → `Asset` (`matched_asset_id`) |

### Relasi modul operasional

| Model | Relasi |
| --- | --- |
| `ObShift` / `ObRoom` | many-to-many via `ob_shift_rooms` |
| `ObChecksheet` | belongsTo `User`, `ObRoom`, `ObShift` |
| `Task` | belongsTo `Vendor`, `User`, `BusinessEntity` |
| `ItemRequest` | belongsTo `User`, `ItemRequestType`, `BusinessEntity`; hasMany `ItemRequestDetail` (→ `Product`), `ItemRequestApproval`, `ItemRequestLog` |
| `ItemRequestType` | belongsTo `Division` (`pic_division_id`) |
| `Product` | belongsTo `ProductCategory`, `UnitType` |
| `ProblemReport` | belongsTo `User`, `ProblemReportCategory`, `BusinessEntity` |
| `Insurance` | belongsTo `InsuranceCategory`, `InsuranceScope`, `InsuranceProvider` (×2: `stock_provider_id`, `building_provider_id`), `User`, `BusinessEntity`; hasMany `InsuranceUpdate` |
| `Rent` | belongsTo `User`, `BusinessEntity`; hasMany `RentUpdate` |

### Tabel bawaan dan soft delete

- **Spatie:** `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.
- **Paket lain:** `activity_log`, `settings`, `pulse_*`, `filament_exceptions_table`, `jobs`, `failed_jobs`, `exports`, `personal_access_tokens`.
- **Soft delete:** semua model fitur kecuali `users` dan `asset_qr_label_histories` (lihat bab Akses). Hapus dari panel hanya mengisi `deleted_at`; filter Sampah memulihkannya.

## Akses & permission
<!-- desc: Login, role, permission Shield, dan batas badan usaha -->

Akses ditentukan **permission** Shield, bukan nama role. Setelah itu dipersempit lagi oleh badan usaha.

### Tiga lapis akses

1. **Masuk panel.** `User::canAccessPanel()` hanya mengizinkan user yang punya minimal satu role. Login lewat username/email, OTP WhatsApp (`PhoneLogin`), atau kode/magic link email (`EmailLogin`). Registrasi, reset password, dan profil default dimatikan.
2. **Permission per aksi.** Policy di `app/Policies` hasil `shield:generate`, format `view_any_asset`, `create_item::request`, dst. Permission custom dibuat lewat migration, misalnya `manage_stock_asset::transfer`, `export_asset`, `view_all_item::request`.
3. **Badan usaha.** `HasBusinessEntityAccess` + `BusinessEntityRule` (aset: `AssetBusinessEntityRule`) di `visibility()` model: user dengan `access_all_business_entities` melihat semua; user lain melihat badan usaha asal + yang dicentang di `business_entity_user`.

### Daftar role

| Role | Asal | Fungsi |
| --- | --- | --- |
| `super_admin` | Seeder | Akses penuh; satu-satunya yang boleh mengubah akses badan usaha user |
| `general_affair` | Dibuat manual | Pihak GA pada BA/BAPEB, aksi perbaikan aset |
| `admin` | Dibuat manual | `export_asset`, `manage_stock_asset::transfer` |
| `pengajuan_admin`, `accounting`, `executor`, `requester` | `sumo:import` | Peran modul Pengajuan Barang dan Lapor Gangguan |

### Lapisan visibilitas (`app/Access`)

Aturan "siapa melihat apa" dirangkai per model, bukan ditulis ulang per resource:

- `Visibility::make()->require(...)->grant(...)`: pembatas (AND, mis. `Rules\BusinessEntityRule`) dan pemberi akses (OR, mis. `Rules\OwnerGrant`, `Rules\ApproverOfOwnerGrant`, `Rules\PermissionGrant`, plus rule modul di `ItemRequests/` dan `ProblemReports/`).
- Setiap `Rule` punya dua wajah yang harus sepakat: `apply()` membatasi query daftar, `allows()` memutuskan satu record dari relasi yang sudah dimuat (null = jatuh ke query). `Grant::grantsEverything()` melewati grup OR bila user melihat semua.
- Model memakai trait `HasVisibility` (`visibleTo()`, `isVisibleTo()`), resource `ScopesVisibleRecords` (mengisi `getEloquentQuery()`; ganti `baseVisibleQuery()` bila perlu, jangan timpa `getEloquentQuery()`), policy `AuthorizesVisibleRecords` (`visible()`).
- `VisibilityArchitectureTest` memastikan ketiganya terpasang untuk setiap model ber-`HasVisibility`; `Tests\Support\AssertsVisibilityContract` membandingkan query dengan keputusan per record (relasi dimuat dan tidak).

Semua model fitur memakainya. Rule khusus: `Assets\AssetBusinessEntityRule` (badan usaha pemilik, penerima, atau pemegang), `Users\HidesSuperAdminsRule` + `Users\BusinessEntityMemberGrant` + `Rules\SelfGrant`, `ObChecksheets\UnclaimedPoolGrant` (kolam tugas bersama), servis aset lewat dua `OwnerGrant` (`created_by`, `serviced_by_user_id`); model lain `Rules\EveryoneGrant`, dibatasi `BusinessEntityRule` bila punya `business_entity_id`. `VisibilityArchitectureTest` menolak resource baru yang modelnya tidak memakai `HasVisibility`, kecuali yang didaftar eksplisit di test itu: Roles dan Exceptions (vendor), Impor Data Sumo dan Rekonsiliasi Aset (alat impor dengan izin sendiri).

### Soft delete sebagai standar

- Semua model fitur memakai `SoftDeletes`; pengecualian eksplisit di `VisibilityArchitectureTest`: `User` (kolom unik identitas, sinkron Talenta/sumo) dan `AssetQrLabelHistory` (log hanya-baca).
- Resource: `TrashedFilter` ("Sampah"), `RestoreAction` per baris, `RestoreBulkAction` dan `ForceDeleteBulkAction` (label Indonesia lewat `configureUsing` di `AppServiceProvider`); test arsitektur merender tiap halaman daftar untuk memastikannya. `getEloquentQuery()` tetap menyembunyikan record terhapus (badge, widget, ekspor), filter yang memunculkannya, dan `getRecordRouteBindingEloquentQuery()` memakai `withTrashed()` supaya halaman view record terhapus bisa dibuka. Record di Sampah hanya-baca: `ScopesVisibleRecords::canEdit()`/`canReplicate()` menolak, policy memakai `editable()` (visible dan tidak terhapus) untuk `update`/`delete`/`replicate`, dan satu aturan global di `AppServiceProvider` (`Action::configureUsing`) menyembunyikan setiap aksi ber-record pada record di Sampah kecuali Lihat, Pulihkan, dan Hapus permanen, sehingga aksi alur kerja (pengajuan, laporan, kondisi aset, gabung, servis, OB, vendor) otomatis menunggu pemulihan. `config/filament-shield.php` mencantumkan `restore`/`forceDelete` di `policies.methods` supaya izinnya tampil dan bertahan di form Role.
- Policy: dua belas metode standar Shield (`viewAny`, `view`, `create`, `update`, `delete`, `deleteAny`, `replicate`, `reorder`, `restore`, `restoreAny`, `forceDelete`, `forceDeleteAny`), dan setiap metode per record memanggil `visible()` atau `editable()`. Izin `restore_*`/`force_delete_*` dibuat migrasi `2026_10_31_000003`: role dan user dengan `delete_*` (termasuk izin langsung dari impor sumo, yang `$crud`-nya kini ikut `restore`) mendapat `restore_*`; `force_delete_*` hanya ke role super admin, karena super admin di proyek ini tidak mem-bypass Gate. Hapus permanen record anak memakai `force_delete_*` induknya.
- Aturan lihat tidak membedakan status terhapus (`Visibility::allows()` menilai record terhapus dengan aturan yang sama).
- Relasi `belongsTo` ke model soft delete memakai `withTrashed()` (riwayat utuh) dan subquery scope yang lewat tabel itu juga (`Division::withTrashed()` di `DivisionScopeGrant`), supaya query dan policy sepakat; select dan filter relasi memakai `modifyQueryUsing` `withoutTrashed()` (tidak bisa memilih yang terhapus). Generator nomor unik (`AssetTransfer::generateLetterNumber`, `AssetService::generateServiceNumber`, `Task::generateTaskCode`, `VehicleChecksheetResource::generateReferenceNumber`, `GeneratesSequentialCode`) menghitung termasuk record terhapus, dan impor yang mencari nilai unik (nama badan usaha, kode gudang) memulihkan baris di Sampah alih-alih menggandakannya.
- Record anak yang soft delete (`InsuranceUpdate`, `RentUpdate`, `ItemRequestDetail`) tidak punya resource: policy-nya memakai `visibleParent()` atau mendelegasikan ke policy induk, punya `restore`/`forceDelete`, dan relation manager yang bisa mengubahnya punya `TrashedFilter`, `RestoreAction`, `ForceDeleteAction`.

### Titik masuk query

Daftar yang dilihat viewer selalu lewat `Resource::getEloquentQuery()` (badge navigasi, widget, ekspor) atau `Resource::scopeToViewer()` (relation manager, halaman scan, service ekspor, exporter Filament); `visibleTo()` tidak dipanggil langsung di luar `app/Access` dan `app/Models`, dan test arsitektur menolaknya. Satu record diperiksa lewat policy (`$this->authorize('view', $record)`, `can('view', ...)`), yang sudah memanggil `visible()`. Kode yang berjalan tanpa user (QR publik `qr/{qr}`, integrasi WhatsApp, pengingat terjadwal, importer sumo/Talenta) sengaja tidak dibatasi aturan lihat, tetapi tetap mengabaikan record di Sampah (QR aset terhapus menjawab 404, pengingat dan WhatsApp hanya aset dan definisi dokumen yang hidup, BA terhapus bukan riwayat terakhir); daftar opsi form (mis. calon pemegang aset) memakai `HasBusinessEntityAccess` langsung karena bukan daftar record.


### Visibilitas modul sumo

Tidak ada role hard-coded seperti di sumo-web. `ItemRequest::visibility()` menentukan siapa melihat pengajuan apa:

- pemilik pengajuan;
- atasannya (`users.approver_user_id`);
- pemegang izin Accounting (`AccountingTypeGrant`, tipe yang lewat tahap Accounting);
- executor/manager/audit sesuai cakupan di `item_request_scopes` (`DivisionScopeGrant`, per tipe dan/atau area);
- pemegang `view_all_item::request` melihat semua.

Cakupan dengan `approves_quantity` menjadikan tahap Manager wajib: hanya divisi itu yang menyetujui jumlah (`ItemRequestWorkflow::canApproveQuantity`), dan executor baru boleh memproses setelah tahap Accounting/Manager yang wajib lewat (`executorMayProcess`, `pendingStageBeforeExecutor`, `scopeAwaitingExecutor`). Importer mengisinya dari elemen ketiga `sumo-import.scopes`.

`ProblemReport::visibility()` serupa: pelapor melihat miliknya; pemegang `view_all_problem::report` melihat semua, kecuali divisinya punya cakupan jenis di `problem_report_scopes` (`DivisionCategoryGrant`; kolom `excluded` = semua jenis kecuali yang disebut; sumo: HCM hanya UMUM, MIS-IT selain UMUM). Cakupan diatur di menu Divisi dan diisi importer dari `sumo-import.problem_report_scopes`.

### Catatan

- Halaman ini dijaga permission `View:TechnicalDocs` (awalnya hanya `super_admin`).
- Menu Impor Data Sumo dijaga permission `Manage:SumoImport` (awalnya hanya `super_admin`).
- Setelah mengubah permission lewat kode, jalankan `php artisan permission:cache-reset`.

## Testing & konvensi
<!-- desc: Menjalankan test, gaya kode, dan alur git -->

PHPUnit dengan dua testsuite: `tests/Unit` dan `tests/Feature` (±86 file). Feature test berjalan di database `.env`, bukan SQLite.

### Menjalankan test

```bash
php artisan test
php artisan test --filter=ItemRequestWorkflowTest
vendor/bin/pint --test
npm run build
```

`phpunit.xml` memaksa queue `sync`, cache/session/mail `array`, tapi **tidak** memaksa SQLite.

Per 27 Oktober 2026 tidak ada test gagal yang diketahui. Bila muncul `Allowed memory size of 134217728 bytes exhausted`, naikkan batas memori PHP: `php -d memory_limit=1G artisan test`.

### Konvensi kode

- Format dengan Laravel Pint (preset default).
- Teks UI dan komentar domain berbahasa Indonesia; nama kelas, kolom, method berbahasa Inggris.
- Status disimpan sebagai enum di `app/Enums`, label diambil dari trait `Utilities`.
- Logika lintas model di `app/Services`; resource cukup memanggilnya.
- Schema hanya diubah lewat migration baru; migration lama tidak diedit.

### Alur git

- Branch dari `main`: `feat/<scope>`, `fix/<scope>`, `docs/<scope>`. Satu tujuan per branch.
- Commit gaya Conventional Commits, bahasa Inggris: `feat: ...`, `fix: ...`, `test: ...`, `chore: ...`.
- Perubahan masuk ke `main` lewat pull request.

### Definition of Done

- [ ] Test area terkait lulus.
- [ ] `pint --test` bersih.
- [ ] `npm run build` sukses bila ada perubahan aset.
- [ ] Tidak ada `.env`, dump database (`*.sql`, `*.gz` di root), atau data pribadi yang ikut ter-commit.

## Maintenance
<!-- desc: Langkah menambah modul dan hal yang perlu diwaspadai -->

Ikuti urutan yang dipakai modul sumo saat menambah fitur, dan cek daftar jebakan sebelum merge.

### Menambah modul atau menu baru

1. **Migration** baru di `database/migrations` (`YYYY_MM_DD_00000N_...`) dengan `softDeletes()` (wajib; `VisibilityArchitectureTest` menolak model fitur tanpa soft delete).
2. **Model** di `app/Models`: `$fillable`, `casts()`, relasi (`->withTrashed()` untuk `belongsTo` ke model soft delete), trait `HasVisibility` + `SoftDeletes`, dan `visibility()` (`BusinessEntityRule` bila punya `business_entity_id`). Status sebagai enum di `app/Enums`.
3. **Resource**: `php artisan make:filament-resource NamaModel --view`, isi `$navigationGroup`, pasang `ScopesVisibleRecords`, `TrashedFilter`, `RestoreAction`, `RestoreBulkAction`, `ForceDeleteBulkAction`; select relasi ke model soft delete memakai `withoutTrashed()`. Grup baru didaftarkan di `navigationGroups()` dan `NavigationGroupsTest`.
4. **Permission**: `php artisan shield:generate --resource=NamaModelResource`, lalu buat `restore_*`/`force_delete_*` lewat migration seperti `2026_10_31_000003`. Permission tambahan juga lewat migration (contoh: `2026_10_13_000007_create_sumo_module_permissions_and_roles.php`). Policy memakai `AuthorizesVisibleRecords` dan `visible()` di setiap aksi per record.
5. **Logika**: workflow di `app/Services/NamaWorkflow.php`; aturan siapa-melihat-apa lewat `visibility()` di model (trait `HasVisibility`, rule di `app/Access/NamaModul`), `ScopesVisibleRecords` di resource, dan `AuthorizesVisibleRecords` di policy. `VisibilityArchitectureTest` menolak bila salah satunya terlewat.
6. **Test** feature untuk resource, policy, dan workflow; aturan lihat diuji dengan `AssertsVisibilityContract`.
7. **Dokumentasi**: tambah bab di `Guide.php` (rekam ulang dengan `tools/guide-capture`) dan perbarui file ini.

### Hal yang perlu diwaspadai

- **Invarian stok aset:** aset Tersedia selalu tanpa `recipient_id`. Pindah pemegang hanya lewat `AssetTransfer::applyLifecycleToAssets`.
- **Badan usaha:** data per badan usaha dibatasi lewat `BusinessEntityRule` di `visibility()` model; jangan menulis query badan usaha manual di resource.
- **File upload:** pakai `StoredFile` dan disk yang dikonfigurasi; production memakai bucket S3 privat.
- **Queue:** tanpa Redis + Horizon, notifikasi dan export menumpuk diam-diam. Impor Data Sumo memakai koneksi `redis-long` dan queue `long-running` (supervisor terpisah di `config/horizon.php`); setelah deploy jalankan `php artisan horizon:terminate`.
- **Data user dari Talenta:** jangan menambah cara lain untuk mengubah field yang dikunci untuk user ber-Employee ID; perubahan harus lewat import JSON Talenta.
- **Endpoint publik:** `POST /api/integrations/whatsapp/assets/query` dan `qr/{qr}` tanpa auth. Jangan tambah data sensitif.

### Sisa pekerjaan migrasi sumo

- [x] Petakan badan usaha CV.CS, PT. RISM, dan TKANAN di `config/sumo-import.php` (CV.CS dan PT. RISM masih perlu dicek ulang, lihat komentar di config).
- [x] Impor data sumo dari panel (menu Pengaturan > Impor Data Sumo).
- [ ] Salin folder upload lama (`Request_File`, `Problem_Report_File`, `Rent_File`, `product`) ke disk public.
- [ ] Pasang cron `schedule:run` tiap menit di server.

### Dokumen terkait

- `README.md`
- `docs/cara-kerja-custom-attribute-pengingat-dokumen.md`
- `docs/s3-production.md`
- `docs/chain-of-truth/`
- `database/sumo/README.md`
