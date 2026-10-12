<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Barang yang bisa diajukan (sumo: products), terutama ATK.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'item_code',
        'name',
        'product_category_id',
        'unit_type_id',
        'price',
        'description',
        'image',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id')->withTrashed();
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class)->withTrashed();
    }

    public function requestDetails(): HasMany
    {
        return $this->hasMany(ItemRequestDetail::class);
    }

    public function label(): string
    {
        $unit = $this->unitType?->name;

        return filled($unit) ? "{$this->name} ({$unit})" : $this->name;
    }
}
