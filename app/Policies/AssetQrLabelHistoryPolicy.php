<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\AssetQrLabelHistory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetQrLabelHistoryPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:AssetQrLabelHistory');
    }

    public function view(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('View:AssetQrLabelHistory') && $this->visible($user, $assetQrLabelHistory);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:AssetQrLabelHistory');
    }

    public function update(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('Update:AssetQrLabelHistory') && $this->editable($user, $assetQrLabelHistory);
    }

    public function delete(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('Delete:AssetQrLabelHistory') && $this->editable($user, $assetQrLabelHistory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:AssetQrLabelHistory');
    }

    public function replicate(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('Replicate:AssetQrLabelHistory') && $this->editable($user, $assetQrLabelHistory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:AssetQrLabelHistory');
    }

    public function forceDelete(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('ForceDelete:AssetQrLabelHistory') && $this->visible($user, $assetQrLabelHistory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:AssetQrLabelHistory');
    }

    public function restore(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('Restore:AssetQrLabelHistory') && $this->visible($user, $assetQrLabelHistory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:AssetQrLabelHistory');
    }
}
