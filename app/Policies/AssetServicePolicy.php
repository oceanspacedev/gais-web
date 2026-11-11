<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\AssetService;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetServicePolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_asset::service');
    }

    public function view(User $user, AssetService $assetService): bool
    {
        return $user->can('view_asset::service') && $this->visible($user, $assetService);
    }

    public function create(User $user): bool
    {
        return $user->can('create_asset::service');
    }

    public function update(User $user, AssetService $assetService): bool
    {
        return $user->can('update_asset::service')
            && $this->editable($user, $assetService)
            && (! $assetService->exists || $this->updateAll($user) || $this->isInvolved($user, $assetService));
    }

    public function delete(User $user, AssetService $assetService): bool
    {
        return $user->can('delete_asset::service')
            && $this->editable($user, $assetService)
            && (! $assetService->exists || $this->deleteAll($user) || $assetService->created_by === $user->id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_asset::service');
    }

    public function forceDelete(User $user, AssetService $assetService): bool
    {
        return $user->can('force_delete_asset::service') && $this->visible($user, $assetService);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_asset::service');
    }

    public function restore(User $user, AssetService $assetService): bool
    {
        return $user->can('restore_asset::service') && $this->visible($user, $assetService);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_asset::service');
    }

    public function export(User $user): bool
    {
        return $user->can('export_asset::service');
    }

    /**
     * Servis milik user lain; tanpa izin ini user hanya melihat servis yang
     * dibuatnya atau ditugaskan kepadanya (lihat AssetService::visibility()).
     */
    public function viewAll(User $user): bool
    {
        return $user->can('view_all_asset::service');
    }

    public function updateAll(User $user): bool
    {
        return $user->can('update_all_asset::service');
    }

    public function deleteAll(User $user): bool
    {
        return $user->can('delete_all_asset::service');
    }

    private function isInvolved(User $user, AssetService $assetService): bool
    {
        return $assetService->created_by === $user->id || $assetService->serviced_by_user_id === $user->id;
    }

    public function replicate(User $user, AssetService $assetService): bool
    {
        return $user->can('replicate_asset::service') && $this->editable($user, $assetService);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_asset::service');
    }
}
