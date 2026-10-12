<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status polis asuransi dan perjanjian sewa beserta riwayat pembaharuannya.
 */
enum ContractStatus: string implements HasColor, HasLabel
{
    case Running = 'BERJALAN';
    case Renewed = 'PEMBAHARUAN';
    case Closed = 'TUTUP';
    case Refunded = 'REFUND';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Berjalan',
            self::Renewed => 'Pembaharuan',
            self::Closed => 'Tutup',
            self::Refunded => 'Refund',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'success',
            self::Renewed => 'info',
            self::Closed => 'gray',
            self::Refunded => 'danger',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Running, self::Renewed], true);
    }

    /**
     * Status yang mengakhiri kontrak (tidak ikut daftar jatuh tempo dan reminder).
     *
     * @return list<string>
     */
    public static function endedValues(): array
    {
        return [self::Closed->value, self::Refunded->value];
    }
}
