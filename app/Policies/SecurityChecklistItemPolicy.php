<?php

namespace App\Policies;

use App\Access\AuthorizesVisibleRecords;
use App\Models\SecurityChecklistItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SecurityChecklistItemPolicy
{
    use AuthorizesVisibleRecords, HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:SecurityChecklistItem');
    }

    public function view(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('View:SecurityChecklistItem') && $this->visible($user, $item);
    }

    public function create(User $user): bool
    {
        return $user->can('Create:SecurityChecklistItem');
    }

    public function update(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('Update:SecurityChecklistItem') && $this->editable($user, $item);
    }

    public function delete(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('Delete:SecurityChecklistItem') && $this->editable($user, $item);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:SecurityChecklistItem');
    }

    public function forceDelete(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('ForceDelete:SecurityChecklistItem') && $this->visible($user, $item);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:SecurityChecklistItem');
    }

    public function restore(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('Restore:SecurityChecklistItem') && $this->visible($user, $item);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:SecurityChecklistItem');
    }

    public function replicate(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('Create:SecurityChecklistItem') && $this->editable($user, $item);
    }

    /**
     * Mengurutkan pertanyaan sama dengan mengeditnya; tidak perlu izin terpisah.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Update:SecurityChecklistItem');
    }
}
