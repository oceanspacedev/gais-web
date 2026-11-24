<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Posisi teks kop surat di samping logo. Logo selalu di kiri; tanpa logo
 * teks selalu di tengah.
 */
enum LetterheadLayout: string implements HasIcon, HasLabel
{
    /** Seperti kop CV TOP SELULAR. */
    case Right = 'right';

    /** Seperti kop PT MEDIA SELULAR INDONESIA. */
    case Center = 'center';

    /** Seperti kop CV MAJU TECNOLOGI. */
    case Left = 'left';

    public function getLabel(): string
    {
        return match ($this) {
            self::Right => 'Teks kanan',
            self::Center => 'Teks tengah',
            self::Left => 'Teks kiri',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Right => 'heroicon-o-bars-3-bottom-right',
            self::Center => 'heroicon-o-bars-3',
            self::Left => 'heroicon-o-bars-3-bottom-left',
        };
    }
}
