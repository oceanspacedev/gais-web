<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\ItemRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemRequestPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ItemRequest');
    }

    public function view(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('View:ItemRequest') && $this->visible($user, $itemRequest);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:ItemRequest');
    }

    public function update(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('Update:ItemRequest') && $this->editable($user, $itemRequest);
    }

    public function delete(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('Delete:ItemRequest') && $this->editable($user, $itemRequest);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:ItemRequest');
    }

    public function forceDelete(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('ForceDelete:ItemRequest') && $this->visible($user, $itemRequest);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:ItemRequest');
    }

    public function restore(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('Restore:ItemRequest') && $this->visible($user, $itemRequest);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:ItemRequest');
    }

    public function replicate(User $user, ItemRequest $itemRequest): bool
    {
        return $user->can('Replicate:ItemRequest') && $this->editable($user, $itemRequest);
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:ItemRequest');
    }

    /**
     * Ekspor daftar ke Excel.
     */
    public function export(User $user): bool
    {
        return $user->can('Export:ItemRequest');
    }

    /**
     * Data milik user lain; tanpa izin ini user hanya melihat data yang terkait dirinya.
     */
    public function viewAll(User $user): bool
    {
        return $user->can('ViewAll:ItemRequest');
    }

    /**
     * Tahap Accounting: isi status dan nomor PO.
     */
    public function approveAccounting(User $user): bool
    {
        return $user->can('ApproveAccounting:ItemRequest');
    }

    /**
     * Tahap Manager: menyetujui jumlah barang.
     */
    public function approveManager(User $user): bool
    {
        return $user->can('ApproveManager:ItemRequest');
    }

    /**
     * Tahap Executor: memproses, menyelesaikan, atau membatalkan.
     */
    public function process(User $user): bool
    {
        return $user->can('Process:ItemRequest');
    }

    /**
     * Mengisi catatan audit.
     */
    public function audit(User $user): bool
    {
        return $user->can('Audit:ItemRequest');
    }

    /**
     * Mengganti pemohon (dicatat di log).
     */
    public function changeApplicant(User $user): bool
    {
        return $user->can('ChangeApplicant:ItemRequest');
    }
}
