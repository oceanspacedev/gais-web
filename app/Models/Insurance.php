<?php

namespace App\Models;

use App\Access\HasVisibility;
use App\Access\Rules\BusinessEntityRule;
use App\Access\Rules\EveryoneGrant;
use App\Access\Visibility;
use App\Enums\ContractStatus;
use App\Models\Concerns\ComputesInsuranceStock;
use App\Support\ContractDue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Polis asuransi (sumo: insurances) dengan riwayat pembaharuan. Jatuh tempo
 * efektif adalah tanggal berakhir pembaharuan terakhir, bila ada.
 */
class Insurance extends Model
{
    use ComputesInsuranceStock, HasFactory, HasVisibility, SoftDeletes;

    protected $fillable = [
        'policy_number', 'insured_name', 'warehouse_code', 'insured_address', 'insured_detail', 'risk_address',
        'stock_provider_id', 'building_provider_id', 'stock_worth', 'building_worth', 'actual_stock_worth',
        'stock_premium', 'building_premium', 'insurance_category_id', 'insurance_scope_id', 'extension_of_policy',
        'join_date', 'expired_date', 'user_id', 'business_entity_id', 'payment_evidence', 'status', 'notes',
        'reminder_phones',
    ];

    protected $casts = [
        'join_date' => 'date',
        'expired_date' => 'date',
        'status' => ContractStatus::class,
        'stock_worth' => 'integer',
        'building_worth' => 'integer',
        'actual_stock_worth' => 'integer',
        'stock_premium' => 'integer',
        'building_premium' => 'integer',
    ];

    protected $attributes = ['status' => 'BERJALAN'];

    public function stockProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'stock_provider_id')->withTrashed();
    }

    public function buildingProvider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'building_provider_id')->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InsuranceCategory::class, 'insurance_category_id')->withTrashed();
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(InsuranceScope::class, 'insurance_scope_id')->withTrashed();
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
        return $this->hasMany(InsuranceUpdate::class)->orderByDesc('expired_date')->orderByDesc('id');
    }

    /**
     * Kontrak yang belum berakhir: statusnya aktif dan tidak ada pembaharuan
     * berstatus tutup/refund (sumo: beranda dan reminder).
     */
    public function scopeActiveContracts(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', ContractStatus::endedValues())
            ->whereDoesntHave('updates', fn (Builder $updates) => $updates->whereIn('status', ContractStatus::endedValues()));
    }

    /**
     * Polis yang jatuh tempo efektifnya (pembaharuan terakhir, atau polis itu
     * sendiri) sampai sekian hari ke depan, termasuk yang sudah lewat.
     */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->whereRaw(
            'COALESCE((SELECT MAX(u.expired_date) FROM insurance_updates u WHERE u.insurance_id = insurances.id AND u.deleted_at IS NULL), insurances.expired_date) <= ?',
            [today()->addDays($days)->toDateString()],
        );
    }

    public function latestUpdate(): ?InsuranceUpdate
    {
        return $this->updates->first();
    }

    /**
     * Baris yang berlaku sekarang: pembaharuan terakhir bila ada, kalau tidak polis
     * ini sendiri (sumo: daftar asuransi menampilkan nilai pembaharuan terakhir).
     */
    public function effectiveRow(): Insurance|InsuranceUpdate
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
