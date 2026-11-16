<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\BusinessEntityRule;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use App\Enums\ContractStatus;
use App\Support\ContractDue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Polis asuransi kendaraan, tertaut ke satu aset MOBIL/MOTOR, dengan riwayat
 * pembaharuan. Dipisah dari asuransi bangunan (Insurance) yang mencatat polis
 * gudang stok dan bangunan. Jatuh tempo efektif = tanggal berakhir pembaharuan
 * terakhir, bila ada.
 */
class VehicleInsurance extends Model
{
    use HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = [
        'asset_id', 'policy_number', 'insured_name', 'insurance_provider_id', 'insurance_category_id',
        'extension_of_policy', 'insured_worth', 'premium', 'join_date', 'expired_date', 'user_id',
        'business_entity_id', 'payment_evidence', 'status', 'notes', 'reminder_phones',
    ];

    protected $casts = [
        'join_date' => 'date',
        'expired_date' => 'date',
        'status' => ContractStatus::class,
        'insured_worth' => 'integer',
        'premium' => 'integer',
    ];

    protected $attributes = ['status' => 'BERJALAN'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InsuranceCategory::class, 'insurance_category_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class)->withTrashed();
    }

    public function updates(): HasMany
    {
        return $this->hasMany(VehicleInsuranceUpdate::class)->orderByDesc('expired_date')->orderByDesc('id');
    }

    /**
     * Polis yang belum berakhir: statusnya aktif dan tidak ada pembaharuan
     * berstatus tutup/refund.
     */
    public function scopeActiveContracts(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', ContractStatus::endedValues())
            ->whereDoesntHave('updates', fn (Builder $updates) => $updates->whereIn('status', ContractStatus::endedValues()));
    }

    /**
     * Polis yang jatuh tempo efektifnya sampai sekian hari ke depan, termasuk yang sudah lewat.
     */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->whereRaw(
            'COALESCE((SELECT MAX(u.expired_date) FROM vehicle_insurance_updates u WHERE u.vehicle_insurance_id = vehicle_insurances.id AND u.deleted_at IS NULL), vehicle_insurances.expired_date) <= ?',
            [today()->addDays($days)->toDateString()],
        );
    }

    public function latestUpdate(): ?VehicleInsuranceUpdate
    {
        return $this->updates->first();
    }

    /**
     * Baris yang berlaku sekarang: pembaharuan terakhir bila ada, kalau tidak polis ini sendiri.
     */
    public function effectiveRow(): VehicleInsurance|VehicleInsuranceUpdate
    {
        return $this->latestUpdate() ?? $this;
    }

    public function effective(string $attribute): mixed
    {
        return $this->effectiveRow()->{$attribute};
    }

    public function effectiveExpiredDate(): ?CarbonInterface
    {
        return $this->latestUpdate()?->expired_date ?? $this->expired_date;
    }

    public function effectiveStatus(): ?ContractStatus
    {
        return $this->latestUpdate()?->status ?? $this->status;
    }

    public function effectiveDueColor(): ?string
    {
        return ContractDue::insuranceColor($this->effectiveExpiredDate(), $this->effectiveStatus());
    }

    /**
     * Plat nomor aset (atribut "Plat Nomor"), untuk tabel, pencarian, dan pengingat.
     */
    public function licensePlate(): ?string
    {
        $asset = $this->asset;

        if ($asset === null) {
            return null;
        }

        $name = config('vehicle-asset-reconciliation.plate_attribute_name', 'Plat Nomor');

        return $asset->attributes()
            ->whereHas('customAttribute', fn (Builder $query): Builder => $query->where('name', $name))
            ->value('attribute_value');
    }

    /**
     * Subquery plat nomor aset, untuk kolom/pencarian/urutan tabel.
     */
    public static function licensePlateSubquery(): \Illuminate\Database\Query\Builder
    {
        return AssetAttribute::query()
            ->select('asset_attributes.attribute_value')
            ->join('custom_asset_attributes', 'custom_asset_attributes.id', '=', 'asset_attributes.custom_attribute_id')
            ->whereColumn('asset_attributes.asset_id', 'vehicle_insurances.asset_id')
            ->where('custom_asset_attributes.name', config('vehicle-asset-reconciliation.plate_attribute_name', 'Plat Nomor'))
            ->limit(1)
            ->toBase();
    }

    /**
     * Semua pemegang izin melihat record di badan usaha yang bisa ia akses;
     * record tanpa badan usaha terlihat oleh semua.
     */
    public static function visibility(): Visibility
    {
        return Visibility::make()
            ->require(new BusinessEntityRule)
            ->grant(new EveryoneGrant);
    }
}
