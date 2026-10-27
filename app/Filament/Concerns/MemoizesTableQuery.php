<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Filament mengevaluasi query tabel ratusan kali per request (setiap aksi baris
 * menanyakan nama model lewat Table::getModel()). Resource dengan aturan lihat
 * per pengguna ikut menjalankan cek izinnya setiap kali, jadi query dasarnya
 * dibangun sekali per request. Aman karena Table::getQuery() selalu meng-clone
 * query ini sebelum menambah filter, pencarian, dan urutan.
 */
trait MemoizesTableQuery
{
    protected Builder|Relation|null $memoizedTableQuery = null;

    protected function getTableQuery(): Builder|Relation|null
    {
        return $this->memoizedTableQuery ??= parent::getTableQuery();
    }
}
