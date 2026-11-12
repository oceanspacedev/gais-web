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
        return $user->can('view_any_security::checklist::item');
    }

    public function view(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('view_security::checklist::item') && $this->visible($user, $item);
    }

    public function create(User $user): bool
    {
        return $user->can('create_security::checklist::item');
    }

    public function update(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('update_security::checklist::item') && $this->editable($user, $item);
    }

    public function delete(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('delete_security::checklist::item') && $this->editable($user, $item);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_security::checklist::item');
    }

    public function forceDelete(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('force_delete_security::checklist::item') && $this->visible($user, $item);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_security::checklist::item');
    }

    public function restore(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('restore_security::checklist::item') && $this->visible($user, $item);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_security::checklist::item');
    }

    public function replicate(User $user, SecurityChecklistItem $item): bool
    {
        return $user->can('create_security::checklist::item') && $this->editable($user, $item);
    }

    /**
     * Mengurutkan pertanyaan sama dengan mengeditnya; tidak perlu izin terpisah.
     */
    public function reorder(User $user): bool
    {
        return $user->can('update_security::checklist::item');
    }
}
