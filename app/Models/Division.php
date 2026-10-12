<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Division extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'area_id',
    ];

    public function approvers(): HasMany
    {
        return $this->hasMany(DivisionApprover::class)->orderBy('level', 'asc');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Cakupan pengajuan barang yang boleh diproses anggota divisi ini.
     */
    public function itemRequestScopes(): HasMany
    {
        return $this->hasMany(ItemRequestScope::class);
    }
}
