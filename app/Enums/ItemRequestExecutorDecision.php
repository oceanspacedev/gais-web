<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Keputusan executor saat memproses pengajuan (sumo: PENDING/PROCESSED/CLOSED/CANCELLED).
 */
enum ItemRequestExecutorDecision: string implements HasLabel
{
    case Pending = 'PENDING';
    case Processed = 'PROCESSED';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu (kembalikan ke antrean)',
            self::Processed => 'Diproses',
            self::Closed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function clientStatus(): ItemRequestClientStatus
    {
        return match ($this) {
            self::Pending => ItemRequestClientStatus::Waiting,
            self::Processed => ItemRequestClientStatus::Processed,
            self::Closed => ItemRequestClientStatus::Closed,
            self::Cancelled => ItemRequestClientStatus::Cancelled,
        };
    }
}
