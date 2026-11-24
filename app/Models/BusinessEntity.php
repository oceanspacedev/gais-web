<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use App\Enums\LegalForm;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class BusinessEntity extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    public const LETTERHEAD_IMAGE = 'image';

    public const LETTERHEAD_PARENT = 'parent';

    public const LETTERHEAD_COMPLETE = 'complete';

    public const LETTERHEAD_INCOMPLETE = 'incomplete';

    protected $fillable = [
        'name', 'format', 'color', 'letterhead',
        'legal_name', 'legal_form', 'short_name', 'legal_entity_id',
        'address', 'city', 'phone', 'email',
        'npwp', 'nib', 'legal_registration_number', 'logo',
    ];

    protected function casts(): array
    {
        return [
            'legal_form' => LegalForm::class,
        ];
    }

    /**
     * PT pemilik sebuah brand/outlet; kop outlet memakai data PT ini.
     */
    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(self::class, 'legal_entity_id');
    }

    /**
     * Brand/outlet yang memakai kop badan usaha ini.
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(self::class, 'legal_entity_id');
    }

    /**
     * Sumber kop: gambar override, ikut PT induk, data lengkap, atau belum lengkap.
     */
    public function letterheadStatus(): string
    {
        return match (true) {
            filled($this->letterhead) => self::LETTERHEAD_IMAGE,
            $this->legalEntity !== null => self::LETTERHEAD_PARENT,
            filled($this->legal_name) && filled($this->address) && filled($this->phone) && filled($this->logo) => self::LETTERHEAD_COMPLETE,
            default => self::LETTERHEAD_INCOMPLETE,
        };
    }

    public function scopeLetterheadIncomplete(Builder $query): Builder
    {
        // Sejalan dengan letterheadStatus(): spasi saja dihitung kosong, dan PT
        // pemilik yang sudah dihapus (soft delete) tidak dihitung.
        $blank = fn (string $column): string => "TRIM(COALESCE({$column}, '')) = ''";

        return $query
            ->whereRaw($blank('letterhead'))
            ->whereDoesntHave('legalEntity')
            ->where(function (Builder $q) use ($blank) {
                foreach (['legal_name', 'address', 'phone', 'logo'] as $column) {
                    $q->orWhereRaw($blank($column));
                }
            });
    }

    /**
     * Badan usaha yang boleh dipilih $viewer di form: semuanya untuk akses tanpa
     * batas, selain itu hanya yang bisa diaksesnya. $include (nilai record yang
     * sedang diedit) selalu disertakan supaya pilihannya tidak hilang.
     *
     * @return array<int, string>
     */
    public static function optionsFor(?Authenticatable $viewer, mixed $include = null): array
    {
        if (! $viewer instanceof User || $viewer->hasUnrestrictedBusinessEntityAccess()) {
            // Kunci cache dipakai bersama (dan di-forget) di tempat lain, kadang
            // berisi Collection, kadang array.
            return collect(Cache::remember(
                'business_entity_options',
                300,
                fn () => static::query()->orderBy('name')->pluck('name', 'id')->all(),
            ))->all();
        }

        $ids = $viewer->accessibleBusinessEntityIds();

        if (filled($include)) {
            $ids[] = (int) $include;
        }

        return static::query()
            ->whereKey($ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    // Relasi ke tabel asset_transfers
    public function assetTransfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    /**
     * Users that belong to this business entity (users.business_entity_id).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Users granted access to this business entity on top of their own.
     */
    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Semua record terlihat; pembatasan hanya lewat izin Shield di policy.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()->grant(new EveryoneGrant);
    }
}
