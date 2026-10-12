<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tipe pengajuan (sumo: ASSET/NONASSET, ATK, NOTA). Flag-nya menentukan alur:
 * wajib lampiran, wajib daftar barang, dan lewat tahap Accounting.
 */
class ItemRequestType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'pic_division_id',
        'requires_attachment',
        'requires_products',
        'requires_accounting_approval',
        'is_active',
    ];

    protected $casts = [
        'requires_attachment' => 'boolean',
        'requires_products' => 'boolean',
        'requires_accounting_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function picDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'pic_division_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ItemRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
