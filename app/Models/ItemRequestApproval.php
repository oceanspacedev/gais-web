<?php

namespace App\Models;

use App\Enums\ItemRequestApprovalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemRequestApproval extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['item_request_id', 'approval_type', 'approved_by_user_id', 'approved_at'];

    protected $casts = [
        'approval_type' => ItemRequestApprovalType::class,
        'approved_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class, 'item_request_id')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isApproved(): bool
    {
        return $this->approved_by_user_id !== null;
    }

    public function markApproved(User $user): void
    {
        $this->forceFill(['approved_by_user_id' => $user->id, 'approved_at' => now()])->save();
    }

    public function reset(): void
    {
        $this->forceFill(['approved_by_user_id' => null, 'approved_at' => null])->save();
    }
}
