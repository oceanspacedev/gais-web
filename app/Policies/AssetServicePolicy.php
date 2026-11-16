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
        return $user->can('ViewAny:AssetService');
    }

    public function view(User $user, AssetService $assetService): bool
    {
        return $user->can('View:AssetService') && $this->visible($user, $assetService);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:AssetService');
    }

    public function update(User $user, AssetService $assetService): bool
    {
        return $user->can('Update:AssetService')
            && $this->editable($user, $assetService)
            && (! $assetService->exists || $this->updateAll($user) || $this->isInvolved($user, $assetService));
    }

    public function delete(User $user, AssetService $assetService): bool
    {
        return $user->can('Delete:AssetService')
            && $this->editable($user, $assetService)
            && (! $assetService->exists || $this->deleteAll($user) || $assetService->created_by === $user->id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:AssetService');
    }

    public function forceDelete(User $user, AssetService $assetService): bool
    {
        return $user->can('ForceDelete:AssetService') && $this->visible($user, $assetService);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:AssetService');
    }

    public function restore(User $user, AssetService $assetService): bool
    {
        return $user->can('Restore:AssetService') && $this->visible($user, $assetService);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:AssetService');
    }

    public function export(User $user): bool
    {
        return $user->can('Export:AssetService');
    }

    /**
     * Servis milik user lain; tanpa izin ini user hanya melihat servis yang
     * dibuatnya atau ditugaskan kepadanya (lihat AssetService::visibility()).
     */
    public function viewAll(User $user): bool
    {
        return $user->can('ViewAll:AssetService');
    }

    public function updateAll(User $user): bool
    {
        return $user->can('UpdateAll:AssetService');
    }

    public function deleteAll(User $user): bool
    {
        return $user->can('DeleteAll:AssetService');
    }

    private function isInvolved(User $user, AssetService $assetService): bool
    {
        return $assetService->created_by === $user->id || $assetService->serviced_by_user_id === $user->id;
    }

    public function replicate(User $user, AssetService $assetService): bool
    {
        return $user->can('Replicate:AssetService') && $this->editable($user, $assetService);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:AssetService');
    }
}
