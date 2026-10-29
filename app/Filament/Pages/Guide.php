<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Panduan cara kerja GAIS untuk siapa pun yang memegang aplikasi ini.
 * Terbuka untuk semua user yang sudah login ke panel, tanpa permission khusus.
 */
class Guide extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Panduan';

    protected static ?string $title = 'Panduan GAIS';

    protected static ?string $slug = 'panduan';

    protected string $view = 'filament.pages.guide';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * Langkah tiap alur animasi. Kunci `actor` menentukan warna lencana.
     *
     * @return array<string, array<int, array{actor: string, title: string, text: string}>>
     */
    public function flows(): array
    {
        return [
            'login' => [
                ['actor' => 'user', 'title' => 'Buka halaman login', 'text' => 'Pilih masuk dengan username/email, atau tombol WhatsApp (muncul bila WAG_URL dan WAG_TOKEN terisi).'],
                ['actor' => 'user', 'title' => 'Masukkan nomor HP', 'text' => 'Di /phone-login. Nomor dinormalisasi ke kode negara 62.'],
                ['actor' => 'sistem', 'title' => 'OTP 6 digit dikirim', 'text' => 'Lewat WhatsApp, disimpan sebagai hash, berlaku 5 menit. Maksimal 3 kiriman per nomor per 2 menit.'],
                ['actor' => 'user', 'title' => 'Masukkan OTP', 'text' => 'Salah lebih dari 5 kali, kode hangus dan harus minta ulang.'],
                ['actor' => 'sistem', 'title' => 'Cek akun', 'text' => 'Nomor harus sama dengan "Nomor WhatsApp login" di akun, dan akun punya minimal satu role. Jika cocok, user masuk ke /admin.'],
            ],
            'request' => [
                ['actor' => 'pemohon', 'title' => 'Buat pengajuan', 'text' => 'Pilih tipe, isi barang dan jumlah, unggah lampiran bila tipenya mewajibkan. Kode otomatis REQ{id}. Maksimal 2 pengajuan terbuka per tipe per pemohon.'],
                ['actor' => 'sistem', 'title' => 'Rantai persetujuan dibuat', 'text' => 'Empat baris approval dibuat otomatis: Accounting, Manager, Executor, dan Pemohon. Status awal: Menunggu.'],
                ['actor' => 'accounting', 'title' => 'Accounting (bila tipe butuh)', 'text' => 'Mengisi status PO dan nomor PO lalu menyetujui. Tipe tanpa syarat Accounting melewati tahap ini.'],
                ['actor' => 'manager', 'title' => 'Manager menyetujui', 'text' => 'Jumlah disetujui otomatis sama dengan jumlah diminta bila kosong. Jumlah per barang bisa diubah di tab Barang.'],
                ['actor' => 'executor', 'title' => 'Executor memproses', 'text' => 'Memilih keputusan: Diproses, Selesai, Dibatalkan, atau kembali ke Pending. Selesai dan Dibatalkan mencatat siapa dan kapan.'],
                ['actor' => 'pemohon', 'title' => 'Pemohon konfirmasi', 'text' => 'Setelah barang diterima, pemohon menekan konfirmasi. Status menjadi Diterima dan pengajuan tertutup.'],
            ],
            'problem' => [
                ['actor' => 'pemohon', 'title' => 'Pelapor membuat laporan', 'text' => 'Wajib pilih kategori dan unggah foto sebelum. Kode PR{id}. Satu pelapor hanya boleh punya satu laporan yang belum dikonfirmasi.'],
                ['actor' => 'executor', 'title' => 'Executor memproses', 'text' => 'Mengisi status, jadwal pengerjaan, foto sesudah, dan hasil pekerjaan.'],
                ['actor' => 'executor', 'title' => 'Laporan ditutup', 'text' => 'Status Closed. Bila dibatalkan, laporan langsung dianggap terkonfirmasi.'],
                ['actor' => 'pemohon', 'title' => 'Pelapor konfirmasi', 'text' => 'Pelapor memberi review dan mengonfirmasi hasil.'],
                ['actor' => 'sistem', 'title' => 'Konfirmasi otomatis', 'text' => 'Bila pelapor diam, command problem-reports:auto-confirm (jalan tiap 10 menit) mengonfirmasi laporan yang sudah ditutup lebih dari 24 jam.'],
            ],
            'asset' => [
                ['actor' => 'sistem', 'title' => 'Aset di stok', 'text' => 'Kondisi Tersedia dan tidak punya pemegang. Aset Tersedia selalu disimpan tanpa pemegang.'],
                ['actor' => 'ga', 'title' => 'BA serah terima', 'text' => 'Staf General Affair mengeluarkan aset dari stok ke pemegang baru. Aset menjadi Digunakan.'],
                ['actor' => 'pemohon', 'title' => 'BAPAB pengalihan', 'text' => 'Pemegang mengalihkan aset ke orang lain. Tetap Digunakan, pemegang berganti.'],
                ['actor' => 'ga', 'title' => 'BAPEB pengembalian', 'text' => 'Pemegang mengembalikan aset ke staf GA. Aset kembali ke stok tanpa pemegang (Tersedia).'],
            ],
            'ob' => [
                ['actor' => 'manager', 'title' => 'Atasan menugaskan', 'text' => 'Per shift dan ruangan. Tugas bisa untuk OB tertentu atau masuk kolam bersama.'],
                ['actor' => 'executor', 'title' => 'OB mengambil tugas', 'text' => 'Tugas kolam bersama diambil lewat tombol klaim. OB hanya melihat tugasnya sendiri dan kolam bersama.'],
                ['actor' => 'executor', 'title' => 'Mulai kerja', 'text' => 'Unggah foto sebelum. Status Pending menjadi In progress dan waktu mulai tercatat.'],
                ['actor' => 'executor', 'title' => 'Selesai', 'text' => 'Unggah foto sesudah. Status Completed dan durasi dihitung otomatis.'],
            ],
            'reminder' => [
                ['actor' => 'sistem', 'title' => 'Scheduler jalan tiap jam', 'text' => 'Command reminder:whatsapp. Hanya mengirim sekali sehari, setelah jam kirim di pengaturan.'],
                ['actor' => 'sistem', 'title' => 'Cari yang jatuh tempo', 'text' => 'Sewa dan asuransi pada hari H dan H-n (default 30, 14, 7, 1 hari), serta cutoff pengajuan (default 3 dan 1 hari).'],
                ['actor' => 'sistem', 'title' => 'Kirim WhatsApp', 'text' => 'Ke nomor pengingat milik kontrak. Bila kosong, ke nomor global di pengaturan. Cutoff pengajuan selalu ke nomor global.'],
                ['actor' => 'sistem', 'title' => 'Catat log', 'text' => 'Tiap kiriman masuk wa_notification_logs. Satu nomor hanya menerima satu pengingat per referensi per hari.'],
            ],
            'arch' => [
                ['actor' => 'user', 'title' => 'Browser staf', 'text' => 'Membuka /admin. Semua layar adalah Filament + Livewire.'],
                ['actor' => 'sistem', 'title' => 'Policy dan Shield', 'text' => 'Setiap halaman dan aksi dicek ke permission role, lalu dipersempit per badan usaha.'],
                ['actor' => 'sistem', 'title' => 'Model Eloquent', 'text' => 'Aturan bisnis ada di model dan service (mis. ItemRequestWorkflow, ProblemReportWorkflow).'],
                ['actor' => 'sistem', 'title' => 'MySQL', 'text' => 'Satu database db_gais. Berkas unggahan ke disk lokal atau S3.'],
                ['actor' => 'sistem', 'title' => 'Queue (Redis + Horizon)', 'text' => 'Export, email, dan notifikasi antre di sini. Tanpa php artisan horizon, job tidak diproses.'],
            ],
            'import' => [
                ['actor' => 'admin', 'title' => 'Unggah dump', 'text' => 'Menu Pengaturan, Impor Data Sumo, tombol Unggah dump. File .sql atau .gz disimpan privat dan tercatat sebagai satu run.'],
                ['actor' => 'sistem', 'title' => 'Muat ke staging', 'text' => 'Job antrean long-running mengosongkan database staging sumo lalu memuat dump. Database GAIS ditolak sebagai target.'],
                ['actor' => 'sistem', 'title' => 'Uji coba otomatis', 'text' => 'Semua langkah dijalankan lalu di-rollback. Laporan jumlah, pemetaan badan usaha, dan peringatan user tampil di run.'],
                ['actor' => 'admin', 'title' => 'Impor sungguhan', 'text' => 'Ketik IMPOR. Satu transaksi dan idempoten: memakai id sumo atau legacy_sumo_user_id, aman diulang.'],
                ['actor' => 'sistem', 'title' => 'Beri role dan izin', 'text' => 'Role sumo dipetakan ke pengajuan_admin, accounting, executor, requester. Cache permission direset.'],
            ],
        ];
    }
}
