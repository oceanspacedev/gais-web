<?php

// Terjemahan paket eightynine/filament-excel-import (tombol Impor Excel di
// asuransi, sewa, barang, checksheet kendaraan, dan penjualan aset). Tanpa
// file ini notifikasi hasil impor tampil sebagai kunci mentah
// "excel-import::excel-import.import_success".
return [
    // Label aksi impor
    'import_action_heading' => 'Impor Excel',
    'import_action_description' => 'Unggah file Excel berkolom template untuk mengimpor data.',
    'excel_data' => 'Data Excel',
    'download_sample_excel_file' => 'Unduh contoh file Excel',

    // Judul notifikasi hasil impor
    'import_failed' => 'Impor gagal',
    'import_warning' => 'Peringatan impor',
    'import_information' => 'Informasi impor',
    'import_success' => 'Impor berhasil',

    // Pesan sukses
    'import_success_message' => 'Semua baris berhasil diimpor.',

    // Pesan validasi
    'validation_failed' => 'Baris :row tidak lolos validasi: :messages',

    // Kesalahan file
    'file_empty_error' => 'File yang diunggah kosong atau tidak berisi data yang valid.',
    'header_read_error' => 'Baris judul file yang diunggah tidak terbaca.',
    'missing_headers_error' => 'Kolom wajib tidak ada: :missing. Kolom yang diharapkan: :expected',
];
