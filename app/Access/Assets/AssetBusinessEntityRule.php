<?php

namespace App\Access\Assets;

use App\Access\Rule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Aset yang boleh dilihat user berakses terbatas: badan usaha pemilik, badan
 * usaha penerima (BA terakhir / penanda stok), atau badan usaha pemegangnya
 * termasuk akses user, supaya aset yang dipakai lintas badan usaha tetap
 * terlihat oleh semua pihak yang mengurusnya.
 */
final class AssetBusinessEntityRule implements Rule
{
    public function appliesTo(User $user): bool
    {
        return ! $user->hasUnrestrictedBusinessEntityAccess();
    }

    public function apply(Builder $query, User $user): void
    {
        $ids = $user->accessibleBusinessEntityIds();

        $query->where(fn (Builder $scoped): Builder => $scoped
            ->whereIn($query->qualifyColumn('business_entity_id'), $ids)
            ->orWhereIn($query->qualifyColumn('recipient_business_entity_id'), $ids)
            ->orWhereIn($query->qualifyColumn('recipient_id'), User::query()->select('users.id')->whereIn('business_entity_id', $ids)));
    }

    public function allows(User $user, Model $record): ?bool
    {
        if ($user->canAccessBusinessEntity($record->getAttribute('business_entity_id'))
            || $user->canAccessBusinessEntity($record->getAttribute('recipient_business_entity_id'))) {
            return true;
        }

        if ($record->getAttribute('recipient_id') === null) {
            return false;
        }

        if (! $record->relationLoaded('recipient')) {
            return null;
        }

        return $user->canAccessBusinessEntity($record->getRelation('recipient')?->business_entity_id);
    }
}
