<?php

namespace App\Policies;

use App\Models\ItemRequest;
use App\Models\User;
use App\Support\ItemRequestVisibility;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_item::request');
    }

    public function view(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('view_item::request') && $this->isVisibleTo($user, $itemRequest);
    }

    public function create(User $user): bool
    {
        return $user->can('create_item::request');
    }

    public function update(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('update_item::request') && $this->isVisibleTo($user, $itemRequest);
    }

    public function delete(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('delete_item::request') && $this->isVisibleTo($user, $itemRequest);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_item::request');
    }

    public function forceDelete(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('force_delete_item::request') && $this->isVisibleTo($user, $itemRequest);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_item::request');
    }

    public function restore(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('restore_item::request') && $this->isVisibleTo($user, $itemRequest);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_item::request');
    }

    public function replicate(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('replicate_item::request') && $this->isVisibleTo($user, $itemRequest);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_item::request');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('export_item::request');
    }

    /**
     * Data milik user lain; tanpa izin ini user hanya melihat data yang terkait dirinya.
     */
    public function viewAll(User $user): bool
    {
        return $user->can('view_all_item::request');
    }

    /**
     * Tahap Accounting: isi status dan nomor PO.
     */
    public function approveAccounting(User $user): bool
    {
        return $user->can('approve_accounting_item::request');
    }

    /**
     * Tahap Manager: menyetujui jumlah barang.
     */
    public function approveManager(User $user): bool
    {
        return $user->can('approve_manager_item::request');
    }

    /**
     * Tahap Executor: memproses, menyelesaikan, atau membatalkan.
     */
    public function process(User $user): bool
    {
        return $user->can('process_item::request');
    }

    /**
     * Mengisi catatan audit.
     */
    public function audit(User $user): bool
    {
        return $user->can('audit_item::request');
    }

    /**
     * Mengganti pemohon (dicatat di log).
     */
    public function changeApplicant(User $user): bool
    {
        return $user->can('change_applicant_item::request');
    }

    /**
     * Record yang tidak termasuk cakupan user (visibleTo) atau badan usahanya
     * ditolak. Model yang belum tersimpan, yaitu cek izin saja, tidak dibatasi.
     */
    private function isVisibleTo(User $user, ItemRequest $itemRequest): bool
    {
        return ! $itemRequest->exists || ItemRequestVisibility::allows($user, $itemRequest);
    }
}
