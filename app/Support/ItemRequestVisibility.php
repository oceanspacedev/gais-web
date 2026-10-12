<?php

namespace App\Support;

use App\Models\ItemRequest;
use App\Models\ItemRequestScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Siapa melihat pengajuan apa, pengganti aturan role/divisi hard-coded di sumo:
 *  - "Lihat Data Semua User" melihat semuanya;
 *  - pemohon melihat miliknya, atasan melihat milik bawahannya (users.approver_user_id);
 *  - pemegang izin Accounting melihat tipe yang lewat tahap Accounting;
 *  - executor/manager/audit melihat cakupan divisinya (item_request_scopes:
 *    tipe dan/atau area pemohon; baris kosong = semua).
 * Pembatasan badan usaha ditambahkan terpisah oleh resource.
 */
final class ItemRequestVisibility
{
    public static function apply(Builder $query, User $user): Builder
    {
        if ($user->can('view_all_item::request')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user): void {
            $visible->where('item_requests.user_id', $user->id);

            $visible->orWhereHas('user', fn (Builder $requester) => $requester->where('approver_user_id', $user->id));

            if ($user->can('approve_accounting_item::request')) {
                $visible->orWhereHas('type', fn (Builder $type) => $type->where('requires_accounting_approval', true));
            }

            foreach (self::scopesFor($user) as $scope) {
                $visible->orWhere(function (Builder $scoped) use ($scope): void {
                    if ($scope->coversEverything()) {
                        $scoped->whereNotNull('item_requests.id');

                        return;
                    }

                    if ($scope->item_request_type_id !== null) {
                        $scoped->where('item_requests.item_request_type_id', $scope->item_request_type_id);
                    }

                    if ($scope->area_id !== null) {
                        $scoped->whereHas('user.division', fn (Builder $division) => $division->where('area_id', $scope->area_id));
                    }
                });
            }
        });
    }

    public static function allows(User $user, ItemRequest $request): bool
    {
        if (! $request->isAccessibleBy($user)) {
            return false;
        }

        return ItemRequest::query()->whereKey($request->getKey())->visibleTo($user)->exists();
    }

    /**
     * @return Collection<int, ItemRequestScope>
     */
    private static function scopesFor(User $user)
    {
        $processes = $user->can('process_item::request')
            || $user->can('approve_manager_item::request')
            || $user->can('audit_item::request');

        if ($user->division_id === null || ! $processes) {
            return ItemRequestScope::query()->whereRaw('1 = 0')->get();
        }

        return ItemRequestScope::query()->where('division_id', $user->division_id)->get();
    }
}
