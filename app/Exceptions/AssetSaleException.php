<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aturan penjualan aset antar badan usaha menolak pencatatan (pembeli sama
 * dengan pemilik, aset belum di stok, badan usaha di luar akses, dan
 * sebagainya). Pesannya ditulis untuk pengguna dan aman ditampilkan apa
 * adanya; error lain (database, HTTP) tidak memakai kelas ini.
 */
class AssetSaleException extends RuntimeException {}
