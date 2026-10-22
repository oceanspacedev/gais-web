<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cakupan pengajuan yang boleh dilihat/diproses anggota sebuah divisi:
 * tipe tertentu dan/atau area pemohon tertentu (null = semua).
 */
class ItemRequestScope extends Model
{
    use HasFactory;

    protected $fillable = ['division_id', 'item_request_type_id', 'area_id'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ItemRequestType::class, 'item_request_type_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function coversEverything(): bool
    {
        return $this->item_request_type_id === null && $this->area_id === null;
    }
}
