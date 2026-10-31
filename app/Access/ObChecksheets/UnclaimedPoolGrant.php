<?php

namespace App\Access\ObChecksheets;

use App\Access\Grant;
use App\Models\ObChecksheet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tugas kolam bersama: ditugaskan atasan tanpa petugas dan belum diambil
 * siapa pun, terlihat oleh semua petugas OB supaya bisa diambil.
 */
final class UnclaimedPoolGrant implements Grant
{
    public function appliesTo(User $user): bool
    {
        return true;
    }

    public function grantsEverything(User $user): bool
    {
        return false;
    }

    public function apply(Builder $query, User $user): void
    {
        $query->whereNull($query->qualifyColumn('user_id'))
            ->where($query->qualifyColumn('source'), ObChecksheet::SOURCE_ASSIGNED);
    }

    public function allows(User $user, Model $record): ?bool
    {
        return $record->getAttribute('user_id') === null
            && $record->getAttribute('source') === ObChecksheet::SOURCE_ASSIGNED;
    }
}
