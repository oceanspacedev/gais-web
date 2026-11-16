<?php

namespace App\Access\ItemRequests;

use App\Access\Grant;
use App\Models\Division;
use App\Models\ItemRequestScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Executor, manager, dan auditor melihat pengajuan dalam cakupan divisinya
 * (item_request_scopes, diatur di menu Divisi): tipe tertentu dan/atau area
 * divisi pengajuan tertentu; baris tanpa tipe dan area = semua pengajuan.
 */
final class DivisionScopeGrant implements Grant
{
    private const PERMISSIONS = ['Process:ItemRequest', 'ApproveManager:ItemRequest', 'Audit:ItemRequest'];

    public function appliesTo(User $user): bool
    {
        return $this->scopes($user)->isNotEmpty();
    }

    public function grantsEverything(User $user): bool
    {
        return $this->scopes($user)->contains(fn (ItemRequestScope $scope): bool => $scope->coversEverything());
    }

    public function apply(Builder $query, User $user): void
    {
        foreach ($this->scopes($user) as $scope) {
            $query->orWhere(function (Builder $scoped) use ($scope): void {
                if ($scope->item_request_type_id !== null) {
                    $scoped->where($scoped->qualifyColumn('item_request_type_id'), $scope->item_request_type_id);
                }

                if ($scope->area_id !== null) {
                    $scoped->whereIn(
                        $scoped->qualifyColumn('division_id'),
                        Division::withTrashed()->select('divisions.id')->where('area_id', $scope->area_id),
                    );
                }
            });
        }
    }

    /**
     * Butuh divisi pengajuan (tabel memuat relasi division); pengajuan tanpa
     * divisi hanya masuk cakupan yang tidak membatasi area.
     */
    public function allows(User $user, Model $record): ?bool
    {
        if ($record->getAttribute('division_id') !== null && ! $record->relationLoaded('division')) {
            return null;
        }

        $areaId = $record->getAttribute('division_id') !== null ? $record->getRelation('division')?->area_id : null;

        foreach ($this->scopes($user) as $scope) {
            if ($scope->matches($record->getAttribute('item_request_type_id'), $areaId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, ItemRequestScope>
     */
    private function scopes(User $user): Collection
    {
        if ($user->division_id === null || ! $this->holdsAnyPermission($user)) {
            return new Collection;
        }

        return ItemRequestScope::forDivision((int) $user->division_id);
    }

    private function holdsAnyPermission(User $user): bool
    {
        foreach (self::PERMISSIONS as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
