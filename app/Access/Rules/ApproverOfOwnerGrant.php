<?php

namespace App\Access\Rules;

use App\Access\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Atasan (users.approver_user_id) melihat record milik bawahannya.
 */
final class ApproverOfOwnerGrant implements Grant
{
    public function __construct(
        private readonly string $column = 'user_id',
        private readonly string $relation = 'user',
    ) {}

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
        $query->whereIn(
            $query->qualifyColumn($this->column),
            User::query()->select('users.id')->where('approver_user_id', $user->getKey()),
        );
    }

    public function allows(User $user, Model $record): ?bool
    {
        if (! $record->relationLoaded($this->relation)) {
            return null;
        }

        $owner = $record->getRelation($this->relation);

        return $owner instanceof User && (int) $owner->approver_user_id === (int) $user->getKey();
    }
}
