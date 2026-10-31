<?php

namespace App\Access\Rules;

use App\Access\Rule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pembatas badan usaha (lihat HasBusinessEntityAccess): user tanpa flag "akses
 * semua badan usaha" hanya melihat record di badan usaha yang bisa ia akses.
 * Record tanpa badan usaha (mis. data lama sumo yang tak terpetakan) terlihat
 * oleh semua bila $allowsNull.
 */
final class BusinessEntityRule implements Rule
{
    public function __construct(
        private readonly string $column = 'business_entity_id',
        private readonly bool $allowsNull = true,
    ) {}

    public function appliesTo(User $user): bool
    {
        return ! $user->hasUnrestrictedBusinessEntityAccess();
    }

    public function apply(Builder $query, User $user): void
    {
        $column = $query->qualifyColumn($this->column);
        $ids = $user->accessibleBusinessEntityIds();

        if (! $this->allowsNull) {
            $query->whereIn($column, $ids);

            return;
        }

        $query->where(fn (Builder $scoped): Builder => $scoped
            ->whereNull($column)
            ->orWhereIn($column, $ids));
    }

    public function allows(User $user, Model $record): ?bool
    {
        $id = $record->getAttribute($this->column);

        if ($id === null) {
            return $this->allowsNull;
        }

        return $user->canAccessBusinessEntity($id);
    }
}
