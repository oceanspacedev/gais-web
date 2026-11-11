<?php

namespace App\Access\Rules;

use App\Access\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Record yang adalah user itu sendiri (model User): akun sendiri selalu terlihat.
 */
final class SelfGrant implements Grant
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
        $query->whereKey($user->getKey());
    }

    public function allows(User $user, Model $record): ?bool
    {
        return $record->is($user);
    }
}
