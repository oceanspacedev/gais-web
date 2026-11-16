<?php

namespace App\Access\Rules;

use App\Access\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Anggota divisi/toko melihat record divisinya (kolom division_id pada record,
 * diisi saat dibuat). Pengganti akun bersama toko: riwayat toko terlihat oleh
 * semua karyawan toko itu, siapa pun yang membuatnya.
 */
final class DivisionMemberGrant implements Grant
{
    public function __construct(private readonly string $column = 'division_id') {}

    public function appliesTo(User $user): bool
    {
        return $user->division_id !== null;
    }

    public function grantsEverything(User $user): bool
    {
        return false;
    }

    public function apply(Builder $query, User $user): void
    {
        $query->where($query->qualifyColumn($this->column), $user->division_id);
    }

    public function allows(User $user, Model $record): ?bool
    {
        $division = $record->getAttribute($this->column);

        return $division !== null && (int) $division === (int) $user->division_id;
    }
}
