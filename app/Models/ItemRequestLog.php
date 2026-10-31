<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jejak perubahan pengajuan yang perlu dipertanggungjawabkan (mis. ganti pemohon).
 */
class ItemRequestLog extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = ['item_request_id', 'user_id', 'activity'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class, 'item_request_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
