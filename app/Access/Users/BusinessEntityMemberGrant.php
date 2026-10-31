<?php

namespace App\Access\Users;

use App\Access\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * User di badan usaha yang bisa diakses viewer (lihat HasBusinessEntityAccess);
 * viewer berakses semua badan usaha melihat semua user.
 */
final class BusinessEntityMemberGrant implements Grant
{
    public function appliesTo(User $user): bool
    {
        return true;
    }

    public function grantsEverything(User $user): bool
    {
        return $user->hasUnrestrictedBusinessEntityAccess();
    }

    public function apply(Builder $query, User $user): void
    {
        $query->whereIn($query->qualifyColumn('business_entity_id'), $user->accessibleBusinessEntityIds());
    }

    public function allows(User $user, Model $record): ?bool
    {
        return $user->canAccessBusinessEntity($record->getAttribute('business_entity_id'));
    }
}
