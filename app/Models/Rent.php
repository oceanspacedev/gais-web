<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Perjanjian sewa (sumo: rents) dengan riwayat perpanjangan.
 */
class Rent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rent_code', 'rented_address', 'rented_detail', 'first_party', 'second_party', 'rent_per_year', 'cvcs_fund',
        'online_fund', 'join_date', 'expired_date', 'deduction_evidence', 'deduction_evidence_file', 'document',
        'document_file', 'payment_evidence_file', 'status', 'month_before_reminder', 'user_id', 'business_entity_id',
        'notes', 'reminder_phones',
    ];

    protected $casts = [
        'join_date' => 'date',
        'expired_date' => 'date',
        'status' => ContractStatus::class,
        'rent_per_year' => 'integer',
        'cvcs_fund' => 'integer',
        'online_fund' => 'integer',
    ];

    protected $attributes = ['status' => 'BERJALAN'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(RentUpdate::class)->orderByDesc('expired_date')->orderByDesc('id');
    }

    public function scopeActiveContracts(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', ContractStatus::endedValues())
            ->whereDoesntHave('updates', fn (Builder $updates) => $updates->whereIn('status', ContractStatus::endedValues()));
    }

    public function latestUpdate(): ?RentUpdate
    {
        return $this->updates->first();
    }

    public function effectiveExpiredDate(): ?CarbonInterface
    {
        return $this->latestUpdate()?->expired_date ?? $this->expired_date;
    }

    public function effectiveStatus(): ?ContractStatus
    {
        return $this->latestUpdate()?->status ?? $this->status;
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->business_entity_id === null || $user->canAccessBusinessEntity($this->business_entity_id);
    }
}
