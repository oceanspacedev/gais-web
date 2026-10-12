<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master shift OB beserta daftar ruangan bawaannya. Jam hanya keterangan,
 * tidak membatasi jam kerja OB.
 */
class ObShift extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_location_id',
        'name',
        'start_time',
        'end_time',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function shiftRooms(): HasMany
    {
        return $this->hasMany(ObShiftRoom::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(ObRoom::class, 'ob_shift_rooms')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('ob_rooms.name');
    }

    /**
     * @param  Builder<ObShift>  $query
     * @return Builder<ObShift>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->location ? "{$this->name} — {$this->location->name}" : $this->name;
    }

    public function timeRange(): ?string
    {
        if (blank($this->start_time) && blank($this->end_time)) {
            return null;
        }

        return substr((string) $this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5);
    }

    /**
     * Pilihan shift aktif, mis. "Shift Pagi — HEAD OFFICE PETRATEAN". $include
     * (shift record yang sedang diedit) tetap disertakan walau sudah nonaktif.
     *
     * @return array<int, string>
     */
    public static function options(mixed $include = null): array
    {
        return static::query()
            ->with('location')
            ->where(fn (Builder $query) => $query
                ->where('is_active', true)
                ->when(filled($include), fn (Builder $query) => $query->orWhere('id', $include)))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ObShift $shift): array => [$shift->id => $shift->label()])
            ->all();
    }

    /**
     * Ruangan aktif shift ini, urut sesuai master.
     *
     * @return array<int, string>
     */
    public function roomOptions(): array
    {
        return $this->rooms()
            ->where('ob_rooms.is_active', true)
            ->get()
            ->mapWithKeys(fn (ObRoom $room): array => [$room->id => $room->name])
            ->all();
    }
}
