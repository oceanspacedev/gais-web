<?php

namespace App\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tombol unduh Excel dari sebuah export Maatwebsite yang punya fileName()
 * (sumo: tombol export per modul). Hak akses diatur pemanggil lewat visible().
 */
final class DownloadExcelAction
{
    /**
     * @param  Closure(): object  $export  export dengan method fileName()
     */
    public static function make(string $name, string $label, Closure $export): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function () use ($export): BinaryFileResponse {
                $file = $export();

                return Excel::download($file, $file->fileName());
            });
    }
}
