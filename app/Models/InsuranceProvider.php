<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsuranceProvider extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name'];

    public function stockInsurances(): HasMany
    {
        return $this->hasMany(Insurance::class, 'stock_provider_id');
    }

    public function buildingInsurances(): HasMany
    {
        return $this->hasMany(Insurance::class, 'building_provider_id');
    }
}
