<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = ['name', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id')->withTrashed();
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
