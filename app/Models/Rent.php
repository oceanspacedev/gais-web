<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\ComputesRentBilling;
use App\Support\ContractDue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Perjanjian sewa (sumo: rents) dengan riwayat perpanjangan. Kode RENT#### dibuat
 * otomatis bila kosong, seperti di sumo.
 */
class Rent extends Model
{
    use ComputesRentBilling, HasFactory, SoftDeletes;

    public const CODE_PREFIX = 'RENT';

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

    protected static function booted(): void
    {
        static::creating(function (self $rent): void {
            if (blank($rent->rent_code)) {
                $rent->rent_code = static::nextCode();
            }
        });
    }

    /**
     * Kode berikutnya (sumo: "RENT" + urutan empat digit dari jumlah data), dilewati
     * bila kodenya sudah dipakai.
     */
    public static function nextCode(): string
    {
        $number = static::withTrashed()->count();

        do {
            $code = self::CODE_PREFIX.str_pad((string) (++$number), 4, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('rent_code', $code)->exists());

        return $code;
    }

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

    /**
     * Sewa yang jatuh tempo efektifnya (perpanjangan terakhir, atau kontrak itu
     * sendiri) sampai sekian hari ke depan, termasuk yang sudah lewat.
     */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->whereRaw(
            'COALESCE((SELECT MAX(u.expired_date) FROM rent_updates u WHERE u.rent_id = rents.id AND u.deleted_at IS NULL), rents.expired_date) <= ?',
            [today()->addDays($days)->toDateString()],
        );
    }

    public function latestUpdate(): ?RentUpdate
    {
        return $this->updates->first();
    }

    /**
     * Baris yang berlaku sekarang: perpanjangan terakhir bila ada, kalau tidak
     * kontrak ini sendiri (sumo: daftar sewa menampilkan nilai perpanjangan terakhir).
     */
    public function effectiveRow(): Rent|RentUpdate
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

    public function effectiveReminderDate(): ?CarbonInterface
    {
        return ContractDue::reminderDate($this->effectiveExpiredDate(), $this->effective('month_before_reminder'));
    }

    public function effectiveDueColor(): ?string
    {
        return ContractDue::rentColor($this->effectiveExpiredDate(), $this->effective('month_before_reminder'), $this->effectiveStatus());
    }

    /**
     * Tagihan baris yang berlaku sekarang (sumo: kolom Total/Sisa/Status Tagihan di daftar).
     *
     * @return array{years: int, total: int, paid: int, remaining: int, status: string}
     */
    public function effectiveBillingSummary(): array
    {
        return $this->effectiveRow()->billingSummary();
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->business_entity_id === null || $user->canAccessBusinessEntity($this->business_entity_id);
    }
}
