<?php

namespace App\Access\Assets;

use App\Access\Rule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Penjualan aset antar badan usaha terlihat oleh kedua pihak: user berakses
 * terbatas melihat penjualan yang badan usaha penjual ATAU pembelinya termasuk
 * aksesnya, supaya pemilik lama tetap bisa menelusuri aset yang sudah dilepas
 * dan pemilik baru melihat dari mana asetnya datang.
 */
final class AssetSalePartyRule implements Rule
{
    public function appliesTo(User $user): bool
    {
        return ! $user->hasUnrestrictedBusinessEntityAccess();
    }

    public function apply(Builder $query, User $user): void
    {
        $ids = $user->accessibleBusinessEntityIds();

        $query->where(fn (Builder $scoped): Builder => $scoped
            ->whereIn($query->qualifyColumn('from_business_entity_id'), $ids)
            ->orWhereIn($query->qualifyColumn('to_business_entity_id'), $ids));
    }

    public function allows(User $user, Model $record): ?bool
    {
        return $user->canAccessBusinessEntity($record->getAttribute('from_business_entity_id'))
            || $user->canAccessBusinessEntity($record->getAttribute('to_business_entity_id'));
    }
}
