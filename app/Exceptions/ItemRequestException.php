<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan alur pengajuan barang; pesannya aman ditampilkan ke user.
 */
class ItemRequestException extends RuntimeException {}
