<?php

namespace App\Access;

use App\Models\User;

/**
 * Aturan yang memberi akses (OR): record terlihat bila salah satu Grant yang
 * berlaku untuk user mengenainya.
 */
interface Grant extends Rule
{
    /**
     * True bila grant ini memberi $user semua record, sehingga grup OR tidak
     * perlu ditambahkan ke query sama sekali (mis. izin "Lihat Data Semua User").
     */
    public function grantsEverything(User $user): bool;
}
