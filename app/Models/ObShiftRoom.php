<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObShiftRoom extends Model
{
    protected $fillable = [
        'ob_shift_id',
        'ob_room_id',
        'sort_order',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ObShift::class, 'ob_shift_id')->withTrashed();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ObRoom::class, 'ob_room_id')->withTrashed();
    }
}
