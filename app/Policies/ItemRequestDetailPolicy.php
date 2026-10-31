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

        if ($request->trashed() || $detail->trashed() || in_array($request->client_status, [ItemRequestClientStatus::Accepted, ItemRequestClientStatus::Cancelled], true)) {
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
        return ! $detail->trashed() && $this->manageable($user, $detail);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('update_item::request');
    }

    /**
     * Memulihkan atau menghapus permanen barang mengikuti aturan hapus:
     * pengajuan masih menunggu dan pemohon atau pemegang izin ubah.
     */
    public function restore(User $user, ItemRequestDetail $detail): bool
    {
        return $this->manageable($user, $detail);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('update_item::request');
    }

    /**
     * Hapus permanen mengikuti izin hapus permanen pengajuan induknya.
     */
    public function forceDelete(User $user, ItemRequestDetail $detail): bool
    {
        return $user->can('force_delete_item::request') && $this->manageable($user, $detail);
    }

    /**
     * Pengajuan induk masih menunggu (dan tidak di Sampah), oleh pemohon atau pemegang izin ubah.
     */
    private function manageable(User $user, ItemRequestDetail $detail): bool
    {
        $request = $detail->request;

        return $request instanceof ItemRequest
            && ! $request->trashed()
            && $request->client_status === ItemRequestClientStatus::Waiting
            && ($request->isOwnedBy($user) || $user->can('update', $request));
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_item::request');
    }
}
