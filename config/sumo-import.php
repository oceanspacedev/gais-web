<?php

/*
 * Pemetaan data legacy sumo-web (Web Gais lama) untuk `php artisan sumo:import`.
 * Id sumo dipertahankan untuk area, divisi, master, dan transaksi; hanya user
 * dan badan usaha yang dipetakan ke record GAIS.
 */

$crud = fn (string $subject, string ...$extra): array => array_map(
    fn (string $action): string => "{$action}_{$subject}",
    ['view_any', 'view', 'create', 'update', 'delete', 'delete_any', ...$extra],
);

return [
    'connection' => env('SUMO_DB_CONNECTION', 'sumo'),

    /*
     * Menu Pengaturan > Impor Data Sumo. File dump disimpan di `disk` (privat), lalu
     * job di antrean panjang memuatnya ke database koneksi di atas (staging) dan
     * menjalankan uji coba. Tanpa Redis (mis. tes), job memakai koneksi antrean default.
     */
    'panel' => [
        'disk' => env('SUMO_IMPORT_DISK', env('FILESYSTEM_DISK', 'local')),
        'directory' => 'sumo-imports',
        'queue_connection' => env('SUMO_IMPORT_QUEUE_CONNECTION', env('QUEUE_CONNECTION') === 'redis' ? 'redis-long' : null),
        'queue' => 'long-running',
        // KB. Batas unggah PHP (upload_max_filesize, post_max_size) dan Livewire (12 MB) juga berlaku.
        'max_upload_kb' => (int) env('SUMO_IMPORT_MAX_UPLOAD_KB', 12288),
    ],

    /*
     * badan_usahas.id (sumo) => business_entities.id (GAIS). null = tidak dipetakan.
     * Nama sumo ditulis di komentar supaya mudah dicek; ubah bila pemetaannya keliru.
     */
    'business_entities' => [
        1 => null, // -
        2 => 3,    // CV.CS => CV COMPLETE SELULAR (periksa: bisa juga CV BERSAMA CS, id 13)
        3 => 4,    // CV.TOP SELULAR => CV TOP SELULAR
        4 => 2,    // PT.MKLI => PT. MAJU KENDARAAN LISTRIK INDONESIA
        5 => 9,    // COMPLETEME => COMPLETE ME
        6 => 8,    // AMAZY
        7 => 10,   // TKANAN => TOKO MAS AN AN (format TKN/; nama lengkap user sumo "tkanan" = TOKO MAS AN AN)
        8 => 44,   // CMULIA => COMPLETE MULIA PURWAKARTA
        9 => 5,    // PT. RISM => PT. RETAIL INDONESIA SELALU MAJU (GAIS punya duplikat id 39)
        10 => 1,   // CV MAJU TECNOLOGI
        11 => 18,  // MOMOYO
        12 => 23,  // KOPI ULON => ULON - OMEGA SQUARE
        13 => 20,  // PT TOP SELULAR MAJU
        14 => 29,  // PT KREASI LIMA CAHAYA
        15 => 6,   // PT MEDIA SELULAR INDONESIA
        16 => 30,  // PT COMPLETE SOLUSI NUSANTARA => PT. COMPLETE SOLUSI NUSANTARA
    ],

    // roles.id (sumo) => nama role Shield.
    'roles' => [
        1 => 'pengajuan_admin', // ADMIN
        2 => 'accounting',      // ACCOUNTING
        3 => 'executor',        // EXECUTOR
        4 => 'requester',       // USER
    ],

    // Role sumo yang bekerja lintas badan usaha: dapat "akses semua badan usaha".
    'access_all_business_entities_roles' => [1, 2, 3],

    // Flag alur per nama tipe pengajuan sumo (request_type.request_type).
    'type_flags' => [
        'ASSET/NONASSET' => ['requires_attachment' => true, 'requires_products' => false, 'requires_accounting_approval' => true],
        'ATK' => ['requires_attachment' => false, 'requires_products' => true, 'requires_accounting_approval' => false],
        'NOTA' => ['requires_attachment' => true, 'requires_products' => false, 'requires_accounting_approval' => false],
    ],

    /*
     * Izin tambahan untuk executor berdasarkan nama divisinya (sumo: aturan
     * division_id hard-coded). HCM mengelola asuransi, sewa, master, pengaturan,
     * user dan divisi, serta melihat widget kontrak di beranda; AUDIT mengisi
     * catatan audit; MIS-IT melihat semua pengajuan.
     */
    'division_permissions' => [
        'HCM' => [
            'view_any_user', 'view_user', 'create_user', 'update_user', 'export_user',
            'view_any_division', 'view_division', 'create_division', 'update_division',
            'view_any_business::entity', 'view_business::entity',
            'widget_InsuranceCostChart', 'widget_ExpiringInsurancesTable', 'widget_ExpiringRentsTable',
            ...$crud('insurance', 'export', 'import'),
            ...$crud('insurance::provider'),
            ...$crud('insurance::category'),
            ...$crud('insurance::scope'),
            ...$crud('rent', 'export', 'import'),
            ...$crud('product', 'export', 'import'),
            ...$crud('product::category'),
            ...$crud('unit::type'),
            ...$crud('item::request::type'),
            ...$crud('item::request::setting'),
            ...$crud('problem::report::category'),
            ...$crud('area'),
            'view_any_item::request::log',
            'view_item::request::log',
            'View:WaNotificationSettings',
        ],
        'AUDIT' => ['audit_item::request'],
        'MIS-IT' => ['view_all_item::request'],
    ],

    // roles.id (sumo) yang menerima division_permissions; di sumo aturan divisi hanya untuk EXECUTOR.
    'division_permission_roles' => [3],

    // users.id (sumo) => izin khusus (sumo: user 75 boleh membuka menu asuransi).
    'legacy_user_permissions' => [
        75 => ['view_any_insurance', 'view_insurance'],
    ],

    /*
     * Cakupan pengajuan per divisi (item_request_scopes): divisions.id (sumo) =>
     * daftar [request_type_id|null, area_id|null, menyetujui_jumlah?]. null = semua.
     * Elemen ketiga true = divisi ini menyetujui jumlah (tahap Manager) untuk cakupan
     * itu dan executor menunggu persetujuannya (sumo: ATK dari area TOP, MKLI-DEPO,
     * MKLI-4S, MKLI-HO menunggu WHM/MKLI-HO; NOTA menunggu AUDIT).
     * Tipe: 1 ASSET/NONASSET, 2 ATK, 3 NOTA. Area: 4 TOP, 5 MKLI-DEPO, 6 MKLI-4S,
     * 11 MKLI-HO, 14 CV MAJU TECNOLOGI, 19 PT MEDIA SELULAR INDONESIA.
     */
    'scopes' => [
        6 => [[null, null]],                                     // HCM: semua
        11 => [[null, null]],                                    // MIS-IT: semua
        12 => [[3, null, true]],                                 // AUDIT: menyetujui jumlah NOTA
        9 => [[2, 4, true], [2, 5, true], [2, 14], [2, 19]],     // WHM: ATK; menyetujui jumlah untuk TOP dan MKLI-DEPO
        80 => [[2, 6, true], [2, 11, true]],                     // MKLI-HO: menyetujui jumlah ATK area MKLI
        1 => [[null, 14], [null, 4]],                            // divisi "-": area tertentu
    ],

    /*
     * Penggabungan user manual: users.id (sumo) => users.id (GAIS), atau false
     * supaya selalu dibuat akun baru. Tanpa entri, impor menggabungkan otomatis bila
     * username sama, atau bila nama lengkap (minimal dua kata) hanya dipakai satu
     * user di masing-masing sisi. Akun sumo yang sudah dihapus hanya lewat sini.
     */
    'user_links' => [],

    // Akhiran username untuk akun sumo yang sudah dihapus (tetap dibuat demi riwayat).
    'inactive_username_suffix' => '-nonaktif-sumo',
];
