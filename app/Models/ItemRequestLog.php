<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Jejak perubahan pengajuan yang perlu dipertanggungjawabkan (mis. ganti pemohon).
 */
class ItemRequestLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['item_request_id', 'user_id', 'activity'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class, 'item_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
