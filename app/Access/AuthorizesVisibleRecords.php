<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy model ber-HasVisibility: setiap aksi per record (view, update, delete,
 * ...) memeriksa izin Shield DAN visible().
 */
trait AuthorizesVisibleRecords
{
    /**
     * Record di luar cakupan lihat user ditolak. Model yang belum tersimpan,
     * yaitu cek izin saja, tidak dibatasi.
     */
    protected function visible(User $user, Model $record): bool
    {
        return ! $record->exists || $record->isVisibleTo($user);
    }

    /**
     * Record di Sampah hanya-baca: boleh dilihat, dipulihkan, atau dihapus
     * permanen, tetapi tidak diubah, dihapus lagi, atau direplikasi.
     */
    protected function editable(User $user, Model $record): bool
    {
        return $this->notTrashed($record) && $this->visible($user, $record);
    }

    protected function notTrashed(Model $record): bool
    {
        return ! (method_exists($record, 'trashed') && $record->trashed());
    }

    /**
     * Record anak (detail, pembaharuan) mengikuti cakupan lihat induknya.
     * Induk yang hilang atau model yang belum tersimpan tidak dibatasi.
     */
    protected function visibleParent(User $user, Model $record, string $relation): bool
    {
        if (! $record->exists) {
            return true;
        }

        $parent = $record->getRelationValue($relation);

        return $parent === null || $parent->isVisibleTo($user);
    }
}
