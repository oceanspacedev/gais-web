<?php

namespace App\Policies;

use App\Enums\ItemRequestClientStatus;
use App\Models\ItemRequest;
use App\Models\ItemRequestDetail;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Barang dalam pengajuan: pemohon atau pemegang izin ubah boleh menambah dan
 * menghapus selama pengajuan masih menunggu; manager boleh mengubah jumlah
 * disetujui selama pengajuan belum final.
 */
class ItemRequestDetailPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_item::request');
    }

    public function view(User $user, ItemRequestDetail $detail): bool
    {
        return $detail->request === null || $user->can('view', $detail->request);
    }

    public function create(User $user): bool
    {
        return $user->can('create_item::request') || $user->can('update_item::request');
    }

    public function update(User $user, ItemRequestDetail $detail): bool
    {
        $request = $detail->request;

        if (! $request instanceof ItemRequest) {
            return false;
        }

        if (in_array($request->client_status, [ItemRequestClientStatus::Accepted, ItemRequestClientStatus::Cancelled], true)) {
            return false;
        }

        if ($user->can('approve_manager_item::request')) {
            return true;
        }

        return $request->client_status === ItemRequestClientStatus::Waiting
            && ($request->isOwnedBy($user) || $user->can('update', $request));
    }

    public function delete(User $user, ItemRequestDetail $detail): bool
    {
        $request = $detail->request;

        return $request instanceof ItemRequest
            && $request->client_status === ItemRequestClientStatus::Waiting
            && ($request->isOwnedBy($user) || $user->can('update', $request));
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_item::request');
    }
}
