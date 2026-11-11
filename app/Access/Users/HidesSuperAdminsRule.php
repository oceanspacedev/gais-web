<?php

namespace App\Access\Users;

use App\Access\Rule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Akun super admin hanya terlihat oleh super admin.
 */
final class HidesSuperAdminsRule implements Rule
{
    public function appliesTo(User $user): bool
    {
        return ! $user->isSuperAdmin();
    }

    public function apply(Builder $query, User $user): void
    {
        $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', self::superAdminRole()));
    }

    public function allows(User $user, Model $record): ?bool
    {
        if (! $record->relationLoaded('roles')) {
            return null;
        }

        return ! $record->getRelation('roles')->contains('name', self::superAdminRole());
    }

    private static function superAdminRole(): string
    {
        return (string) config('filament-shield.super_admin.name', 'super_admin');
    }
}
