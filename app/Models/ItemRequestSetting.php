<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Periode cutoff pengajuan ATK bulanan: pengajuan di luar open_date..closed_date
 * tidak diproses. Ditampilkan di beranda dan dipakai reminder WA.
 */
class ItemRequestSetting extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = ['request_month', 'open_date', 'closed_date', 'request_detail'];

    protected $casts = [
        'request_month' => 'date',
        'open_date' => 'date',
        'closed_date' => 'date',
    ];

    public static function current(): ?self
    {
        return static::query()->orderByDesc('request_month')->orderByDesc('id')->first();
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
