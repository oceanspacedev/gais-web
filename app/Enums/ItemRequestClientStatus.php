<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status akhir pengajuan barang, angkanya sama dengan status_client di sumo.
 */
enum ItemRequestClientStatus: int implements HasColor, HasLabel
{
    case Waiting = 0;
    case Accepted = 1;
    case Cancelled = 2;
    case Processed = 3;
    case Closed = 4;

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu',
            self::Accepted => 'Diterima',
            self::Cancelled => 'Dibatalkan',
            self::Processed => 'Diproses',
            self::Closed => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::Accepted => 'success',
            self::Cancelled => 'danger',
            self::Processed => 'info',
            self::Closed => 'primary',
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

    /**
     * Masih dihitung sebagai pengajuan terbuka (kuota maksimal per tipe).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Waiting, self::Processed, self::Closed], true);
    }

    /**
     * @return list<int>
     */
    public static function openValues(): array
    {
        return [self::Waiting->value, self::Processed->value, self::Closed->value];
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
