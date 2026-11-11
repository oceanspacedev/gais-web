<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetTransferDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_transfer_id',
        'asset_id',
        'equipment',
    ];

    public function assetTransfer()
    {
        return $this->belongsTo(AssetTransfer::class)->withTrashed();
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }
}
