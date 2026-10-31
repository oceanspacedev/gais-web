<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Division extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

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
        return $this->belongsTo(Area::class)->withTrashed();
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

    /**
     * Jenis gangguan yang dilihat pemegang "Lihat Data Semua User" di divisi ini.
     */
    public function problemReportScopes(): HasMany
    {
        return $this->hasMany(ProblemReportScope::class);
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
