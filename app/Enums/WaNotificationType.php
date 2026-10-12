<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WaNotificationType: string implements HasLabel
{
    case Rent = 'RENT';
    case Insurance = 'INSURANCE';
    case RequestCutoff = 'REQUEST_SETTING';
    case Test = 'TEST';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Sewa',
            self::Insurance => 'Asuransi',
            self::RequestCutoff => 'Cutoff Pengajuan',
            self::Test => 'Uji Kirim',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
