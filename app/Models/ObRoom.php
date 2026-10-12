<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Master ruangan yang dibersihkan OB.
 */
class ObRoom extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_location_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class, 'asset_location_id');
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(ObShift::class, 'ob_shift_rooms')->withTimestamps();
    }

    public function checksheets(): HasMany
    {
        return $this->hasMany(ObChecksheet::class);
    }

    /**
     * @param  Builder<ObRoom>  $query
     * @return Builder<ObRoom>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Ruangan di lokasi itu, ditambah ruangan yang lokasinya belum diisi.
     *
     * @param  Builder<ObRoom>  $query
     * @return Builder<ObRoom>
     */
    public function scopeAvailableAt(Builder $query, ?int $locationId): Builder
    {
        return $query->when($locationId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('asset_location_id', $locationId)
            ->orWhereNull('asset_location_id')));
    }

    public function label(): string
    {
        return $this->location ? "{$this->name} — {$this->location->name}" : $this->name;
    }

    public static function normalizeName(?string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    }

    /**
     * Cocokkan nama yang diketik OB ke master tanpa peduli spasi dan huruf besar/kecil.
     * Ruangan di lokasi shift didahulukan; tanpa lokasi, nama yang ada di beberapa
     * lokasi dianggap tidak jelas dan tidak dicocokkan.
     */
    public static function findByName(?string $name, ?int $locationId = null): ?self
    {
        $name = self::normalizeName($name);

        if ($name === '') {
            return null;
        }

        $matches = static::query()
            ->active()
            ->availableAt($locationId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->get();

        if ($locationId !== null) {
            return $matches->firstWhere('asset_location_id', $locationId) ?? $matches->first();
        }

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Nama ruangan aktif untuk saran ketikan.
     *
     * @return array<int, string>
     */
    public static function suggestions(?int $locationId = null): array
    {
        return static::query()
            ->active()
            ->availableAt($locationId)
            ->orderBy('name')
            ->distinct()
            ->pluck('name')
            ->all();
    }

    /**
     * Gabungkan ruangan duplikat/salah ketik ke $target: riwayat checksheet ikut
     * pindah (nama di checksheet ikut dibetulkan), daftar ruangan shift dipindah,
     * lalu ruangan sumbernya dihapus.
     *
     * @param  Collection<int, ObRoom>  $sources
     */
    public static function mergeInto(self $target, Collection $sources): int
    {
        $sources = $sources->reject(fn (ObRoom $room): bool => $room->is($target));

        return DB::transaction(function () use ($target, $sources): int {
            foreach ($sources as $source) {
                ObChecksheet::withTrashed()
                    ->where('ob_room_id', $source->id)
                    ->update(['ob_room_id' => $target->id, 'room' => $target->name]);

                $targetShiftIds = ObShiftRoom::query()->where('ob_room_id', $target->id)->pluck('ob_shift_id');

                ObShiftRoom::query()
                    ->where('ob_room_id', $source->id)
                    ->whereIn('ob_shift_id', $targetShiftIds)
                    ->delete();

                ObShiftRoom::query()
                    ->where('ob_room_id', $source->id)
                    ->update(['ob_room_id' => $target->id]);

                $source->delete();
            }

            return $sources->count();
        });
    }
}
