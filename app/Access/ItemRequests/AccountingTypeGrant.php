<?php

namespace App\Access\ItemRequests;

use App\Access\Grant;
use App\Models\ItemRequestType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemegang izin tahap Accounting melihat semua pengajuan bertipe yang lewat
 * tahap Accounting (item_request_types.requires_accounting_approval).
 */
final class AccountingTypeGrant implements Grant
{
    public function appliesTo(User $user): bool
    {
        return $user->can('approve_accounting_item::request');
    }

    public function grantsEverything(User $user): bool
    {
        return false;
    }

    public function apply(Builder $query, User $user): void
    {
        $query->whereIn(
            $query->qualifyColumn('item_request_type_id'),
            ItemRequestType::withTrashed()->select('item_request_types.id')->where('requires_accounting_approval', true),
        );
    }

    public function allows(User $user, Model $record): ?bool
    {
        if (! $record->relationLoaded('type')) {
            return null;
        }

        return (bool) $record->getRelation('type')?->requires_accounting_approval;
    }
}
