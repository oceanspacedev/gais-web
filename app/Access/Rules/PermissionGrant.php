<?php

namespace App\Access\Rules;

use App\Access\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemegang izin Shield tertentu (mis. "Lihat Data Semua User") melihat semua
 * record; pembatas (require) tetap berlaku.
 */
final class PermissionGrant implements Grant
{
    public function __construct(private readonly string $permission) {}

    public function appliesTo(User $user): bool
    {
        return $user->can($this->permission);
    }

    public function grantsEverything(User $user): bool
    {
        return true;
    }

    public function apply(Builder $query, User $user): void
    {
        // Tidak pernah dipanggil Visibility karena grantsEverything(); klausa ini
        // hanya menjaga grup OR tetap berisi bila dipakai langsung.
        $query->whereNotNull($query->getModel()->getQualifiedKeyName());
    }

    public function allows(User $user, Model $record): ?bool
    {
        return true;
    }
}
