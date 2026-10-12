<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kelompok divisi (HO, RETAIL, TOP, ...) yang dipakai cakupan pengajuan barang.
 */
class Area extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }
}
