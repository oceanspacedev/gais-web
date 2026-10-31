<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model yang aturan lihatnya dirangkai lewat Visibility. Resource memakai
 * scopeVisibleTo() (lewat trait ScopesVisibleRecords), policy memakai
 * isVisibleTo() (lewat trait AuthorizesVisibleRecords); VisibilityArchitectureTest
 * memastikan keduanya terpasang.
 */
trait HasVisibility
{
    /**
     * Aturan lihat model ini: pembatas (require) dan pemberi akses (grant).
     */
    abstract public static function visibility(): Visibility;

    /**
     * Record yang boleh dilihat $user.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return static::visibility()->apply($query, $user);
    }

    /**
     * Keputusan untuk record ini, sepakat dengan scopeVisibleTo().
     */
    public function isVisibleTo(User $user): bool
    {
        return static::visibility()->allows($user, $this);
    }
}
