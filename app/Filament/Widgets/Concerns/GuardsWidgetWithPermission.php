<?php

namespace App\Filament\Widgets\Concerns;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;

/**
 * Widget dasbor hanya tampil bagi pemegang izin `widget_{NamaKelas}` (Shield).
 * Widget yang belum punya izin terdaftar (mis. di test tanpa Shield) memakai
 * aturan bawaan Filament, bukan canAccess() milik Shield yang tidak ada di Widget.
 *
 * Jangan menimpa canView() di widget (parent::canView() akan melompati
 * pemeriksaan izin); timpa hasAnythingToShow() untuk syarat tambahan.
 */
trait GuardsWidgetWithPermission
{
    use HasWidgetShield;

    public static function canView(): bool
    {
        return static::holdsWidgetPermission() && static::hasAnythingToShow();
    }

    public static function holdsWidgetPermission(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

    /**
     * Widget gabungan beberapa menu menimpa ini supaya tetap tersembunyi bila
     * tidak ada satu pun menunya yang boleh dibuka pengguna.
     */
    protected static function hasAnythingToShow(): bool
    {
        return true;
    }
}
