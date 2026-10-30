<?php

return [
    /*
     * Pemegang izin "Lihat Data Semua User" pada laporan gangguan yang divisinya
     * ada di sini hanya melihat jenis gangguan yang disebut; awalan "!" berarti
     * semua jenis kecuali yang disebut. Sumo: executor HCM hanya menangani gangguan
     * UMUM, executor lain (MIS-IT) menangani selain UMUM. Kunci = nama divisi,
     * nilai = nama jenis gangguan.
     */
    'division_categories' => [
        'HCM' => ['UMUM'],
        'MIS-IT' => ['!UMUM'],
    ],
];
