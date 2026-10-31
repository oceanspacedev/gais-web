<?php

namespace App\Access\Rules;

use App\Access\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemilik record (kolom user) melihat miliknya sendiri.
 */
final class OwnerGrant implements Grant
{
    public function __construct(private readonly string $column = 'user_id') {}

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
        $query->where($query->qualifyColumn($this->column), $user->getKey());
    }

    public function allows(User $user, Model $record): ?bool
    {
        $owner = $record->getAttribute($this->column);

        return $owner !== null && (int) $owner === (int) $user->getKey();
    }
}
