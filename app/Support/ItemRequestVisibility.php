<?php

namespace App\Support;

use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestScope;
use App\Models\ItemRequestType;
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
 *
 * apply() dan allows() harus selalu sepakat; ItemRequestVisibilityTest
 * membandingkan keduanya.
 */
final class ItemRequestVisibility
{
    /**
     * Subquery memakai whereIn tanpa korelasi (bukan whereHas/EXISTS per baris)
     * supaya MySQL cukup menghitungnya sekali dan bisa memakai index FK.
     */
    public static function apply(Builder $query, User $user): Builder
    {
        if ($user->can('view_all_item::request')) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user): void {
            $visible->where('item_requests.user_id', $user->id);

            $visible->orWhereIn('item_requests.user_id', User::query()->select('users.id')->where('approver_user_id', $user->id));

            if ($user->can('approve_accounting_item::request')) {
                $visible->orWhereIn('item_requests.item_request_type_id', ItemRequestType::withTrashed()->select('item_request_types.id')->where('requires_accounting_approval', true));
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
                        $scoped->whereIn('item_requests.user_id', User::query()->select('users.id')->whereIn(
                            'division_id',
                            Division::query()->select('divisions.id')->where('area_id', $scope->area_id),
                        ));
                    }
                });
            }
        });
    }

    /**
     * Dipanggil policy untuk setiap record dan setiap aksi di tabel, jadi aturan
     * apply() dievaluasi di PHP dari relasi yang sudah dimuat (tabel memuat
     * user.division dan type). Bila relasinya belum dimuat, jatuh ke query.
     */
    public static function allows(User $user, ItemRequest $request): bool
    {
        if (! $request->isAccessibleBy($user)) {
            return false;
        }

        $visible = self::visibleInMemory($user, $request);

        return $visible ?? ItemRequest::query()->whereKey($request->getKey())->visibleTo($user)->exists();
    }

    /**
     * @return bool|null null bila tidak bisa diputuskan tanpa query
     */
    private static function visibleInMemory(User $user, ItemRequest $request): ?bool
    {
        // Query visibleTo memakai scope SoftDeletes, jadi pengajuan terhapus tidak pernah lolos.
        if ($request->trashed()) {
            return false;
        }

        if ($user->can('view_all_item::request') || (int) $request->user_id === $user->getKey()) {
            return true;
        }

        if (! $request->relationLoaded('user') || ! $request->relationLoaded('type')) {
            return null;
        }

        $requester = $request->user;

        if ($requester !== null && (int) $requester->approver_user_id === $user->getKey()) {
            return true;
        }

        if ($request->type?->requires_accounting_approval && $user->can('approve_accounting_item::request')) {
            return true;
        }

        $scopes = self::scopesFor($user);

        if ($scopes->isEmpty()) {
            return false;
        }

        if ($requester !== null && ! $requester->relationLoaded('division')) {
            return null;
        }

        $areaId = $requester?->division?->area_id;

        foreach ($scopes as $scope) {
            if ($scope->matches($request->item_request_type_id, $areaId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cakupan dimemo per divisi selama satu request/job: apply() dipanggil untuk
     * badge navigasi, tabel, widget, dan allows() untuk setiap baris.
     *
     * @return Collection<int, ItemRequestScope>
     */
    private static function scopesFor(User $user): Collection
    {
        $processes = $user->can('process_item::request')
            || $user->can('approve_manager_item::request')
            || $user->can('audit_item::request');

        $divisionId = $user->division_id;

        if ($divisionId === null || ! $processes) {
            return new Collection;
        }

        return once(fn (): Collection => ItemRequestScope::query()->where('division_id', $divisionId)->get());
    }
}
