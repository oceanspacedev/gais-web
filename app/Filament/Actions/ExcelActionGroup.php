<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * Dropdown "Ekspor / Impor" di header halaman daftar, supaya tombol impor dan
 * ekspor tampil sama di semua modul. Grup ikut tersembunyi bila semua isinya
 * tersembunyi; hak akses tetap diatur tiap aksi lewat visible().
 */
final class ExcelActionGroup
{
    public const IMPORT_ICON = 'heroicon-o-arrow-up-tray';

    public const EXPORT_ICON = 'heroicon-o-arrow-down-tray';

    /**
     * @param  array<int, Action>  $actions
     */
    public static function make(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->label('Ekspor / Impor')
            ->icon('heroicon-o-arrows-up-down')
            ->color('gray')
            ->button();
    }
}
