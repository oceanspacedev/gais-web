<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceCategory extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = ['name'];

    public function insurances(): HasMany
    {
        return $this->hasMany(Insurance::class);
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
