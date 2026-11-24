<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Bentuk badan usaha, hanya label/filter. Nama di kop selalu diambil dari
 * nama resmi lengkap (seperti di SK AHU), tidak dirakit dari bentuk ini.
 */
enum LegalForm: string implements HasLabel
{
    case Pt = 'pt';
    case PtPerorangan = 'pt_perorangan';
    case Cv = 'cv';
    case Firma = 'firma';
    case Koperasi = 'koperasi';
    case Ud = 'ud';
    case Brand = 'brand';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pt => 'PT',
            self::PtPerorangan => 'PT Perorangan',
            self::Cv => 'CV',
            self::Firma => 'Firma',
            self::Koperasi => 'Koperasi',
            self::Ud => 'UD',
            self::Brand => 'Brand/Outlet (bukan badan hukum)',
        };
    }
}
