<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Support\ContractDue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pembaharuan polis asuransi kendaraan.
 */
class VehicleInsuranceUpdate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_insurance_id', 'policy_number', 'insurance_provider_id', 'extension_of_policy', 'insured_worth',
        'premium', 'join_date', 'expired_date', 'user_id', 'status', 'payment_evidence', 'notes',
    ];

    protected $casts = [
        'join_date' => 'date',
        'expired_date' => 'date',
        'status' => ContractStatus::class,
        'insured_worth' => 'integer',
        'premium' => 'integer',
    ];

    protected $attributes = ['status' => 'BERJALAN'];

    public function vehicleInsurance(): BelongsTo
    {
        return $this->belongsTo(VehicleInsurance::class)->withTrashed();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(InsuranceProvider::class, 'insurance_provider_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dueColor(): ?string
    {
        return ContractDue::insuranceColor($this->expired_date, $this->status);
    }
}
