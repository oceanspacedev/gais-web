<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemRequestDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_request_id',
        'product_id',
        'qty_request',
        'qty_remaining',
        'qty_approved',
        'description',
    ];

    protected $casts = [
        'qty_request' => 'decimal:2',
        'qty_remaining' => 'decimal:2',
        'qty_approved' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ItemRequest::class, 'item_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Jumlah yang dihitung: disetujui bila sudah diisi, kalau belum jumlah diminta.
     */
    public function effectiveQty(): float
    {
        return (float) ($this->qty_approved ?? $this->qty_request);
    }

    public function lineTotal(): float
    {
        return $this->effectiveQty() * (float) ($this->product?->price ?? 0);
    }
}
