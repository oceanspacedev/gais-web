<?php

namespace App\Access\Rules;

use App\Access\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Semua record terlihat oleh siapa pun yang lolos pembatas (require); izin per
 * aksi tetap diperiksa policy lewat Shield. Dipakai model yang tidak punya
 * aturan per pemilik, mis. master data dan aset.
 */
final class EveryoneGrant implements Grant
{
    public function appliesTo(User $user): bool
    {
        return true;
    }

    public function grantsEverything(User $user): bool
    {
        return true;
    }

    public function apply(Builder $query, User $user): void
    {
        // Tidak pernah dipanggil Visibility karena grantsEverything().
        $query->whereNotNull($query->getModel()->getQualifiedKeyName());
    }

    public function allows(User $user, Model $record): ?bool
    {
        return true;
    }
}
