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
        return $user->can('view_any_asset::qr::label::history');
    }

    public function view(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('view_asset::qr::label::history') && $this->visible($user, $assetQrLabelHistory);
    }

    public function create(User $user): bool
    {
        return $user->can('create_asset::qr::label::history');
    }

    public function update(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('update_asset::qr::label::history') && $this->editable($user, $assetQrLabelHistory);
    }

    public function delete(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('delete_asset::qr::label::history') && $this->editable($user, $assetQrLabelHistory);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_asset::qr::label::history');
    }

    public function replicate(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('replicate_asset::qr::label::history') && $this->editable($user, $assetQrLabelHistory);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_asset::qr::label::history');
    }

    public function forceDelete(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('force_delete_asset::qr::label::history') && $this->visible($user, $assetQrLabelHistory);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_asset::qr::label::history');
    }

    public function restore(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('restore_asset::qr::label::history') && $this->visible($user, $assetQrLabelHistory);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_asset::qr::label::history');
    }
}
