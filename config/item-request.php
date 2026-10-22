<?php

return [
    /*
     * Batas pengajuan yang masih terbuka (menunggu, diproses, selesai tapi belum
     * dikonfirmasi pemohon) per tipe pengajuan untuk satu pemohon. Sumo: 2.
     */
    'max_open_per_type' => (int) env('ITEM_REQUEST_MAX_OPEN_PER_TYPE', 2),

    /*
     * Awalan kode pengajuan dan laporan gangguan (sumo: REQ{id}, PR{id}).
     */
    'code_prefix' => 'REQ',
    'problem_report_code_prefix' => 'PR',
];
