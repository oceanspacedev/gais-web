<?php

namespace App\Models;

use App\Enums\WaNotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaNotificationLog extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'notif_type',
        'reference_id',
        'phone',
        'sent_date',
        'status',
        'message_preview',
        'response_payload',
    ];

    protected $casts = [
        'notif_type' => WaNotificationType::class,
        'sent_date' => 'date',
    ];

    /**
     * Sudah terkirim sukses ke nomor ini untuk referensi yang sama pada tanggal tersebut.
     */
    public function scopeAlreadySent(Builder $query, WaNotificationType $type, ?int $referenceId, string $phone, mixed $date): Builder
    {
        return $query
            ->where('notif_type', $type->value)
            ->where('reference_id', $referenceId)
            ->where('phone', $phone)
            ->whereDate('sent_date', $date)
            ->where('status', self::STATUS_SUCCESS);
    }
}
