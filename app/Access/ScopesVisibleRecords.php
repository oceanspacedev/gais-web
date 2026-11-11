<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Resource Filament yang modelnya memakai HasVisibility: daftar, badge
 * navigasi, widget, dan ekspor yang memanggil getEloquentQuery() otomatis
 * dibatasi ke record yang boleh dilihat viewer. Jangan menimpa
 * getEloquentQuery(); ganti baseVisibleQuery() bila perlu (mis. eager load).
 *
 * Soft delete: getEloquentQuery() tetap menyembunyikan record terhapus (widget
 * dan badge tidak menghitungnya); filter Sampah di tabel yang memunculkannya,
 * dan halaman view/edit bisa membuka record terhapus supaya bisa dipulihkan.
 */
trait ScopesVisibleRecords
{
    public static function getEloquentQuery(): Builder
    {
        return static::scopeToViewer(static::baseVisibleQuery(), auth()->user());
    }

    /**
     * Query sebelum aturan lihat diterapkan.
     */
    protected static function baseVisibleQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    /**
     * Record di Sampah hanya-baca di panel: halaman edit dan aksi Ubah/Replikasi
     * ditolak sampai record dipulihkan (policy menolak lewat editable()).
     */
    public static function canEdit(Model $record): bool
    {
        return ! static::isTrashed($record) && parent::canEdit($record);
    }

    public static function canReplicate(Model $record): bool
    {
        return ! static::isTrashed($record) && parent::canReplicate($record);
    }

    protected static function isTrashed(Model $record): bool
    {
        return method_exists($record, 'trashed') && $record->trashed();
    }

    /**
     * Record terhapus tetap bisa dibuka dari filter Sampah.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $query = static::getEloquentQuery();

        return in_array(SoftDeletes::class, class_uses_recursive(static::getModel()), true)
            ? $query->withTrashed()
            : $query;
    }

    /**
     * Aturan lihat yang sama dengan menu ini, juga dipakai tab di halaman user
     * dan ekspor. Tanpa viewer (tamu, job tanpa user) tidak ada yang terlihat.
     */
    public static function scopeToViewer(Builder $query, mixed $viewer): Builder
    {
        if (! $viewer instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return $query->visibleTo($viewer);
    }
}
